<?php

namespace App\Services\Import;

use App\Enums\Currency;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportEntityType;
use App\Enums\ImportPopulation;
use App\Enums\ImportRowAction;
use App\Enums\ImportRowStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LedgerReason;
use App\Enums\StudentProgramStatus;
use App\Enums\UserStatus;
use App\Enums\WalletKind;
use App\Models\ImportBatch;
use App\Models\ImportLink;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Finance\WalletService;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates the whole batch pipeline: upload -> profile -> map -> validate -> dry
 * run -> commit -> (rollback). See docs/legacy-data-import-plan.md §10.
 *
 * Two entity types are implemented:
 * - STUDENT (L0/L1): identity + program linkage. Rungs 1 and 3 of the matching ladder
 *   (import_links and exact email). Course results, credentials are not yet
 *   implemented; a `program_code` mapping links only to a program that already exists
 *   in the live catalog (the "exact code match" half of D2) and never creates a shadow
 *   program.
 * - BALANCE (L6): finance opening balances. One carried-forward invoice (owed) and/or
 *   one wallet credit (in hand) per student per currency, gated by an exact
 *   control-total match with no acknowledge override — see §8.
 *
 * Every public entry point (`validate`, `dryRun`, `commit`) dispatches on
 * `$batch->entity_type` to the entity-specific private methods below, so adding a
 * further entity type is a matter of adding one more arm to each `match`.
 */
class ImportBatchService
{
    public function __construct(
        private readonly ImportFileReader $reader,
        private readonly ImportProfilerService $profiler,
        private readonly ImportMappingSuggestionService $suggester,
        private readonly ImportTransformService $transforms,
        private readonly ObjectStorageService $storage,
        private readonly WalletService $wallets,
        private readonly AuditLogWriter $audit,
    ) {}

    public function createFromUpload(
        User $actor,
        ImportSource $source,
        UploadedFile $file,
        ?ImportPopulation $population,
        ?string $sheetName = null,
        int $headerRow = 1,
        ImportEntityType $entityType = ImportEntityType::Student,
    ): ImportBatch {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw new RuntimeException('Only .csv and .xlsx files are supported.');
        }

        $contents = file_get_contents($file->getRealPath());
        $hash = hash('sha256', (string) $contents);

        $disk = $this->storage->diskName();
        $path = $this->storage->signedUploadPath('imports', $source->id, $extension);
        $this->storage->store($path, (string) $contents);

        $parsed = $this->reader->read($disk, $path, $sheetName, $headerRow);
        $profile = $this->profiler->profile($parsed['headers'], $parsed['rows']);
        $suggested = $this->suggester->suggest($profile, $source->code);

        return ImportBatch::query()->create([
            'source_id' => $source->id,
            'entity_type' => $entityType,
            'population' => $population,
            'status' => ImportBatchStatus::Draft,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_hash' => $hash,
            'sheet_name' => $sheetName,
            'header_row' => $headerRow,
            'row_count' => count($parsed['rows']),
            'profile' => $profile,
            'mapping' => $suggested,
            'created_by_id' => $actor->id,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function sheetNames(ImportSource $source, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return [];
        }

        $disk = $this->storage->diskName();
        $path = $this->storage->signedUploadPath('imports', $source->id, $extension.'.tmp-sheetscan');
        $this->storage->store($path, (string) file_get_contents($file->getRealPath()));
        $names = $this->reader->sheetNames($disk, $path);
        $this->storage->disk()->delete($path);

        return $names;
    }

    public function findDuplicateHash(ImportSource $source, string $hash): ?ImportBatch
    {
        return ImportBatch::query()
            ->where('source_id', $source->id)
            ->where('file_hash', $hash)
            ->whereIn('status', [ImportBatchStatus::Validated, ImportBatchStatus::DryRun, ImportBatchStatus::Committed])
            ->latest()
            ->first();
    }

    /**
     * @param  array<int, array{column: string, target_field: ?string, transform: string, options?: array<string, mixed>, ignored?: bool}>  $mapping
     */
    public function updateMapping(ImportBatch $batch, array $mapping): ImportBatch
    {
        $batch->update([
            'mapping' => $mapping,
            'status' => ImportBatchStatus::Mapped,
            'mapped_at' => now(),
        ]);

        return $batch->fresh();
    }

    public function saveAsProfile(ImportBatch $batch, User $actor, string $name): void
    {
        \App\Models\ImportMappingProfile::query()->updateOrCreate(
            ['source_id' => $batch->source_id, 'entity_type' => $batch->entity_type, 'name' => $name],
            [
                'mappings' => $batch->mapping,
                'proposed_by' => 'MANUAL',
                'created_by_id' => $actor->id,
            ],
        );
    }

    /**
     * Records the registrar's declared control totals for a BALANCE batch — the
     * amounts they independently computed from the source system, entered before the
     * dry run so the hard gate in §8 has something authoritative to compare against.
     * Stored inside `control_totals` alongside the computed half the dry run later
     * fills in, so the one JSON field keeps the same "declared vs computed" shape the
     * STUDENT entity already uses it for.
     *
     * @param  array<string, array{owed_minor?: int, credit_minor?: int}>  $declared  keyed by currency code, e.g. ['EGP' => ['owed_minor' => 50000, 'credit_minor' => 0]]
     */
    public function setFinanceControlTotals(ImportBatch $batch, string $asOf, array $declared): ImportBatch
    {
        $normalized = [];
        foreach (ImportBalanceFields::SUPPORTED_CURRENCIES as $code) {
            $normalized[$code] = [
                'owed_minor' => (int) ($declared[$code]['owed_minor'] ?? 0),
                'credit_minor' => (int) ($declared[$code]['credit_minor'] ?? 0),
            ];
        }

        $batch->update([
            'control_totals' => [
                'as_of' => $asOf,
                'declared' => $normalized,
            ],
        ]);

        return $batch->fresh();
    }

    /**
     * Re-reads the staged file, applies the mapping, and writes one ImportRow per data
     * row with validation messages. Nothing outside `import_rows` is written here.
     * Dispatches on entity type — see the class docblock.
     */
    public function validate(ImportBatch $batch): ImportBatch
    {
        return match ($batch->entity_type) {
            ImportEntityType::Student => $this->validateStudentRows($batch),
            ImportEntityType::Balance => $this->validateBalanceRows($batch),
        };
    }

    private function validateStudentRows(ImportBatch $batch): ImportBatch
    {
        $disk = $this->storage->diskName();
        $parsed = $this->reader->read($disk, $batch->file_path, $batch->sheet_name, $batch->header_row);
        $mapping = collect($batch->mapping ?? [])->filter(fn ($m) => ! empty($m['target_field']));

        $headerIndex = array_flip($parsed['headers']);
        $seenKeys = [];
        $errorCount = 0;
        $warningCount = 0;

        DB::transaction(function () use ($batch, $parsed, $mapping, $headerIndex, &$seenKeys, &$errorCount, &$warningCount) {
            ImportRow::query()->where('batch_id', $batch->id)->delete();

            foreach ($parsed['rows'] as $i => $row) {
                $normalized = [];
                $rowMessages = [];

                foreach ($mapping as $m) {
                    $colIndex = $headerIndex[$m['column']] ?? null;
                    $raw = $colIndex !== null ? ($row[$colIndex] ?? null) : null;
                    $result = $this->transforms->apply($m['transform'], $raw, $m['options'] ?? []);

                    if (! $result['ok'] && $raw !== null && trim((string) $raw) !== '') {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_BAD_DATE',
                            'field' => $m['target_field'],
                            'params' => ['column' => $m['column'], 'value' => $raw],
                        ];
                    }

                    // A target field produced by more than one mapping (e.g. first_name
                    // and last_name both derived from one full-name column) keeps the
                    // first non-null value already set only if this one is empty.
                    if (! isset($normalized[$m['target_field']]) || $normalized[$m['target_field']] === null) {
                        $normalized[$m['target_field']] = $result['value'];
                    }
                }

                $legacyId = $normalized['legacy_id'] ?? null;
                $hasLegacyId = $legacyId !== null && $legacyId !== '';
                // A legacy id repeated within the file cannot also be the storage key for
                // both import_rows (unique per batch) — the duplicate still needs its own
                // row so the error is visible, so only the first occurrence keeps the bare
                // key; every repeat is disambiguated by row number for storage only. Which
                // key resolves identity at commit time is irrelevant here: a row flagged
                // E_DUPLICATE_NATURAL_KEY is an error and applyRows() never processes it.
                $naturalKey = match (true) {
                    ! $hasLegacyId => 'row-'.($i + 1),
                    isset($seenKeys[$legacyId]) => $legacyId.'#dup-'.($i + 1),
                    default => $legacyId,
                };

                foreach (ImportStudentFields::requiredFor($batch->population) as $required) {
                    if (empty($normalized[$required])) {
                        $code = $required === 'email' ? 'E_ACTIVE_NO_EMAIL' : 'E_REQUIRED_FIELD_MISSING';
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => $code,
                            'field' => $required,
                            'params' => ['field' => $required],
                        ];
                    }
                }

                if ($legacyId !== null && $legacyId !== '') {
                    if (isset($seenKeys[$legacyId])) {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_DUPLICATE_NATURAL_KEY',
                            'field' => 'legacy_id',
                            'params' => ['legacy_id' => $legacyId],
                        ];
                    }
                    $seenKeys[$legacyId] = true;
                }

                if ($batch->population === ImportPopulation::Alumni && empty($normalized['email'])) {
                    $rowMessages[] = [
                        'level' => 'warning',
                        'code' => 'W_NO_EMAIL_ALUMNUS',
                        'field' => 'email',
                        'params' => [],
                    ];
                }

                if (! empty($normalized['program_code'])) {
                    $exists = Program::query()->where('code', $normalized['program_code'])->exists();
                    if (! $exists) {
                        $rowMessages[] = [
                            'level' => 'warning',
                            'code' => 'W_UNKNOWN_PROGRAM_CODE',
                            'field' => 'program_code',
                            'params' => ['program_code' => $normalized['program_code']],
                        ];
                    }
                }

                $hasError = collect($rowMessages)->contains(fn ($m) => $m['level'] === 'error');
                $hasWarning = collect($rowMessages)->contains(fn ($m) => $m['level'] === 'warning');
                if ($hasError) {
                    $errorCount++;
                } elseif ($hasWarning) {
                    $warningCount++;
                }

                ImportRow::query()->create([
                    'batch_id' => $batch->id,
                    'row_number' => $i + 1,
                    'natural_key' => $naturalKey,
                    'payload' => array_combine($parsed['headers'], array_pad($row, count($parsed['headers']), null)),
                    'normalized' => $normalized,
                    'action' => null,
                    'status' => $hasError ? ImportRowStatus::Error : ($hasWarning ? ImportRowStatus::Warn : ImportRowStatus::Valid),
                    'messages' => $rowMessages,
                ]);
            }
        });

        $batch->update([
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'row_count' => count($parsed['rows']),
            'status' => ImportBatchStatus::Validated,
            'validated_at' => now(),
        ]);

        return $batch->fresh();
    }

    /**
     * L6 — finance opening balances. Per row: legacy_id and currency are required
     * (E_REQUIRED_FIELD_MISSING); currency must be exactly EGP or USD
     * (E_UNSUPPORTED_CURRENCY — the importer will not invent an FX rate, §14 Q14); the
     * legacy id must already be linked to a user via a committed STUDENT batch
     * (E_UNKNOWN_STUDENT); owed_minor/credit_minor come through the money_to_minor
     * transform, whose failures surface as E_BAD_MONEY or E_MONEY_PRECISION. A row
     * with neither amount is a no-op, not an error — §8: "no empty invoices".
     */
    private function validateBalanceRows(ImportBatch $batch): ImportBatch
    {
        $disk = $this->storage->diskName();
        $parsed = $this->reader->read($disk, $batch->file_path, $batch->sheet_name, $batch->header_row);
        $mapping = collect($batch->mapping ?? [])->filter(fn ($m) => ! empty($m['target_field']));

        $headerIndex = array_flip($parsed['headers']);
        $seenKeys = [];
        $errorCount = 0;
        $warningCount = 0;

        DB::transaction(function () use ($batch, $parsed, $mapping, $headerIndex, &$seenKeys, &$errorCount, &$warningCount) {
            ImportRow::query()->where('batch_id', $batch->id)->delete();

            foreach ($parsed['rows'] as $i => $row) {
                $normalized = [];
                $rowMessages = [];

                foreach ($mapping as $m) {
                    $colIndex = $headerIndex[$m['column']] ?? null;
                    $raw = $colIndex !== null ? ($row[$colIndex] ?? null) : null;
                    $result = $this->transforms->apply($m['transform'], $raw, $m['options'] ?? []);

                    if (! $result['ok'] && $raw !== null && trim((string) $raw) !== '') {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => $result['code'] ?? 'E_BAD_MONEY',
                            'field' => $m['target_field'],
                            'params' => ['column' => $m['column'], 'value' => $raw],
                        ];
                    }

                    if (! isset($normalized[$m['target_field']]) || $normalized[$m['target_field']] === null) {
                        $normalized[$m['target_field']] = $result['value'];
                    }
                }

                $legacyId = $normalized['legacy_id'] ?? null;
                $hasLegacyId = $legacyId !== null && $legacyId !== '';
                $currency = $normalized['currency'] ?? null;
                $currency = is_string($currency) ? strtoupper(trim($currency)) : $currency;
                $hasCurrency = $currency !== null && $currency !== '';

                $baseKey = $hasLegacyId ? ($legacyId.($hasCurrency ? ':'.$currency : '')) : null;
                $naturalKey = match (true) {
                    $baseKey === null => 'row-'.($i + 1),
                    isset($seenKeys[$baseKey]) => $baseKey.'#dup-'.($i + 1),
                    default => $baseKey,
                };

                foreach (ImportBalanceFields::alwaysRequired() as $required) {
                    if (empty($normalized[$required])) {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_REQUIRED_FIELD_MISSING',
                            'field' => $required,
                            'params' => ['field' => $required],
                        ];
                    }
                }

                if ($hasCurrency && ! in_array($currency, ImportBalanceFields::SUPPORTED_CURRENCIES, true)) {
                    $rowMessages[] = [
                        'level' => 'error',
                        'code' => 'E_UNSUPPORTED_CURRENCY',
                        'field' => 'currency',
                        'params' => ['currency' => $currency],
                    ];
                }

                if ($hasLegacyId) {
                    if (isset($seenKeys[$baseKey])) {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_DUPLICATE_NATURAL_KEY',
                            'field' => 'legacy_id',
                            'params' => ['legacy_id' => $legacyId],
                        ];
                    }
                    $seenKeys[$baseKey] = true;

                    $linked = ImportLink::query()
                        ->where('source_id', $batch->source_id)
                        ->where('entity_type', 'user')
                        ->where('legacy_id', $legacyId)
                        ->exists();

                    if (! $linked) {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_UNKNOWN_STUDENT',
                            'field' => 'legacy_id',
                            'params' => ['legacy_id' => $legacyId],
                        ];
                    }
                }

                $owed = (int) ($normalized['owed_minor'] ?? 0);
                $credit = (int) ($normalized['credit_minor'] ?? 0);
                $normalized['owed_minor'] = $owed;
                $normalized['credit_minor'] = $credit;

                $hasError = collect($rowMessages)->contains(fn ($m) => $m['level'] === 'error');
                $hasWarning = collect($rowMessages)->contains(fn ($m) => $m['level'] === 'warning');
                if ($hasError) {
                    $errorCount++;
                } elseif ($hasWarning) {
                    $warningCount++;
                }

                ImportRow::query()->create([
                    'batch_id' => $batch->id,
                    'row_number' => $i + 1,
                    'natural_key' => $naturalKey,
                    'payload' => array_combine($parsed['headers'], array_pad($row, count($parsed['headers']), null)),
                    'normalized' => $normalized,
                    'action' => ($owed <= 0 && $credit <= 0) ? ImportRowAction::Noop : null,
                    'status' => $hasError ? ImportRowStatus::Error : ($hasWarning ? ImportRowStatus::Warn : ImportRowStatus::Valid),
                    'messages' => $rowMessages,
                ]);
            }
        });

        $batch->update([
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'row_count' => count($parsed['rows']),
            'status' => ImportBatchStatus::Validated,
            'validated_at' => now(),
        ]);

        return $batch->fresh();
    }

    /**
     * Runs the full commit path inside a transaction that is always rolled back, and
     * reports what would happen. Nothing is written — verified by ImportDryRunTest /
     * ImportBatchServiceTest, which assert the row counts are identical before and
     * after. Dispatches on entity type for both the report and the control totals.
     *
     * @return array<string, int>
     */
    public function dryRun(ImportBatch $batch): array
    {
        DB::beginTransaction();
        try {
            $report = $this->applyRows($batch, persist: true);
        } finally {
            DB::rollBack();
        }

        $totals = $this->controlTotals($batch);

        $batch->update([
            'dry_run_report' => $report,
            'control_totals' => $totals,
            'status' => ImportBatchStatus::DryRun,
            'dry_run_at' => now(),
        ]);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function controlTotals(ImportBatch $batch): array
    {
        return match ($batch->entity_type) {
            ImportEntityType::Student => $this->studentControlTotals($batch),
            ImportEntityType::Balance => $this->balanceControlTotals($batch),
        };
    }

    /**
     * @return array{declared_rows: int, computed_rows: int, distinct_students: int}
     */
    private function studentControlTotals(ImportBatch $batch): array
    {
        $valid = ImportRow::query()->where('batch_id', $batch->id)
            ->whereIn('status', [ImportRowStatus::Valid, ImportRowStatus::Warn])
            ->count();

        return [
            'declared_rows' => $batch->row_count,
            'computed_rows' => $valid,
            'distinct_students' => $valid,
        ];
    }

    /**
     * Merges the registrar's already-declared totals (§8's control-total gate input,
     * stored by `setFinanceControlTotals()`) with what this dry run actually computed,
     * so the dry-run screen can show both side by side. This computed half is
     * recomputed independently at commit time by `assertFinanceTotalsMatchExactly()`
     * rather than trusted from here — a stale dry run must not be able to wave through
     * a bad commit.
     *
     * @return array{as_of: ?string, declared: array<string, array{owed_minor: int, credit_minor: int}>, computed: array<string, array{owed_minor: int, credit_minor: int}>, matches: bool}
     */
    private function balanceControlTotals(ImportBatch $batch): array
    {
        $existing = $batch->control_totals ?? [];
        $declared = $existing['declared'] ?? [];
        $computed = $this->financeComputedTotals($batch);

        return [
            'as_of' => $existing['as_of'] ?? null,
            'declared' => $declared,
            'computed' => $computed,
            'matches' => $this->financeTotalsMatch($declared, $computed),
        ];
    }

    /**
     * Sums normalized owed_minor / credit_minor across every row this dry run (or
     * commit) would actually write — i.e. VALID and WARN rows — grouped by currency.
     * Integer arithmetic throughout; nothing here ever touches a float.
     *
     * @return array<string, array{owed_minor: int, credit_minor: int}>
     */
    private function financeComputedTotals(ImportBatch $batch): array
    {
        $totals = [];
        foreach (ImportBalanceFields::SUPPORTED_CURRENCIES as $code) {
            $totals[$code] = ['owed_minor' => 0, 'credit_minor' => 0];
        }

        ImportRow::query()->where('batch_id', $batch->id)
            ->whereIn('status', [ImportRowStatus::Valid, ImportRowStatus::Warn])
            ->get(['normalized'])
            ->each(function (ImportRow $row) use (&$totals) {
                $normalized = $row->normalized ?? [];
                $currency = $normalized['currency'] ?? null;
                if (! is_string($currency) || ! isset($totals[$currency])) {
                    return;
                }

                $totals[$currency]['owed_minor'] += (int) ($normalized['owed_minor'] ?? 0);
                $totals[$currency]['credit_minor'] += (int) ($normalized['credit_minor'] ?? 0);
            });

        return $totals;
    }

    /**
     * @param  array<string, array{owed_minor?: int, credit_minor?: int}>  $declared
     * @param  array<string, array{owed_minor?: int, credit_minor?: int}>  $computed
     */
    private function financeTotalsMatch(array $declared, array $computed): bool
    {
        foreach (ImportBalanceFields::SUPPORTED_CURRENCIES as $code) {
            $declaredOwed = (int) ($declared[$code]['owed_minor'] ?? 0);
            $declaredCredit = (int) ($declared[$code]['credit_minor'] ?? 0);
            $computedOwed = (int) ($computed[$code]['owed_minor'] ?? 0);
            $computedCredit = (int) ($computed[$code]['credit_minor'] ?? 0);

            if ($declaredOwed !== $computedOwed || $declaredCredit !== $computedCredit) {
                return false;
            }
        }

        return true;
    }

    public function commit(User $actor, ImportBatch $batch): ImportBatch
    {
        if (! in_array($batch->status, [ImportBatchStatus::DryRun, ImportBatchStatus::Validated], true)) {
            throw new RuntimeException('Batch must be validated before it can be committed.');
        }

        // The hard control-total gate (§8) — enforced here, not only on the dry-run
        // screen, so a direct POST to the commit route without ever viewing that
        // screen is refused exactly the same way. Recomputed fresh from the current
        // row state rather than trusting a possibly-stale cached dry-run report, and
        // checked before anything is written: on a mismatch, zero rows are touched.
        if ($batch->entity_type === ImportEntityType::Balance) {
            $this->assertFinanceTotalsMatchExactly($batch);
        }

        $this->audit->withAudit($actor, 'import.batch_commit', function () use ($batch, $actor) {
            DB::transaction(function () use ($batch, $actor) {
                $this->applyRows($batch, persist: true, actor: $actor);
            });

            $batch->update([
                'status' => ImportBatchStatus::Committed,
                'committed_by_id' => $actor->id,
                'committed_at' => now(),
                'sealed_at' => now()->addDays(30),
            ]);

            return $batch->fresh();
        }, entityType: ImportBatch::class);

        return $batch->fresh();
    }

    /**
     * The single most important acceptance criterion of L6: a control-total mismatch
     * of even one minor unit, in any currency, refuses commit outright — no
     * acknowledge override exists for money, unlike the STUDENT entity's row-count
     * mismatch. A currency the registrar never declared a total for is compared
     * against zero, so an un-declared currency with real money in the file still
     * fails loudly rather than committing silently.
     */
    private function assertFinanceTotalsMatchExactly(ImportBatch $batch): void
    {
        $declared = $batch->control_totals['declared'] ?? [];
        $computed = $this->financeComputedTotals($batch);

        if (! $this->financeTotalsMatch($declared, $computed)) {
            throw new RuntimeException(__('import.finance_mismatch_refused'));
        }
    }

    /**
     * The single code path behind both the dry run and the real commit — dispatches
     * on entity type. See the per-entity methods below for row-level logic.
     *
     * @return array<string, int>
     */
    private function applyRows(ImportBatch $batch, bool $persist, ?User $actor = null): array
    {
        return match ($batch->entity_type) {
            ImportEntityType::Student => $this->applyStudentRows($batch, $persist),
            ImportEntityType::Balance => $this->applyBalanceRows($batch, $persist, $actor),
        };
    }

    /**
     * Row-level identity resolution: rung 1 (an existing import_links row) then rung 3
     * (exact normalised email match). Anything else is created new. See §6 of the plan
     * for the full ladder — rungs 2, 4-6 (Canvas crosswalk, student number, DOB triple,
     * and the human merge queue) are not yet implemented.
     *
     * @return array{create: int, link: int, skip: int, distinct_students: int}
     */
    private function applyStudentRows(ImportBatch $batch, bool $persist): array
    {
        $create = 0;
        $link = 0;
        $skip = 0;

        /** @var Collection<int, ImportRow> $rows */
        $rows = ImportRow::query()->where('batch_id', $batch->id)
            ->whereIn('status', [ImportRowStatus::Valid, ImportRowStatus::Warn])
            ->orderBy('row_number')
            ->get();

        foreach ($rows as $row) {
            $normalized = $row->normalized ?? [];
            $legacyId = $normalized['legacy_id'] ?? null;

            if ($legacyId === null || $legacyId === '') {
                $skip++;

                continue;
            }

            $existingLink = ImportLink::query()
                ->where('source_id', $batch->source_id)
                ->where('entity_type', 'user')
                ->where('legacy_id', $legacyId)
                ->first();

            if ($existingLink !== null) {
                $link++;
                if ($persist) {
                    $user = User::query()->find($existingLink->target_id);
                    $this->attachProgram($batch, $user, $normalized);
                    $existingLink->update(['last_batch_id' => $batch->id]);
                    $row->update(['action' => ImportRowAction::Link, 'target_type' => User::class, 'target_id' => $user?->id, 'status' => ImportRowStatus::Applied]);
                }

                continue;
            }

            $email = $normalized['email'] ?? null;
            $matchedUser = $email !== null
                ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first()
                : null;

            if ($matchedUser !== null) {
                $link++;
                if ($persist) {
                    ImportLink::query()->create([
                        'source_id' => $batch->source_id,
                        'entity_type' => 'user',
                        'legacy_id' => $legacyId,
                        'target_type' => User::class,
                        'target_id' => $matchedUser->id,
                        'first_batch_id' => $batch->id,
                        'last_batch_id' => $batch->id,
                    ]);
                    $this->attachProgram($batch, $matchedUser, $normalized);
                    $row->update(['action' => ImportRowAction::Link, 'target_type' => User::class, 'target_id' => $matchedUser->id, 'status' => ImportRowStatus::Applied]);
                }

                continue;
            }

            $create++;
            if ($persist) {
                $user = $this->createUser($batch, $normalized, $legacyId);
                ImportLink::query()->create([
                    'source_id' => $batch->source_id,
                    'entity_type' => 'user',
                    'legacy_id' => $legacyId,
                    'target_type' => User::class,
                    'target_id' => $user->id,
                    'first_batch_id' => $batch->id,
                    'last_batch_id' => $batch->id,
                ]);
                $this->attachProgram($batch, $user, $normalized);
                $row->update(['action' => ImportRowAction::Create, 'target_type' => User::class, 'target_id' => $user->id, 'status' => ImportRowStatus::Applied]);
            }
        }

        return [
            'create' => $create,
            'link' => $link,
            'skip' => $skip,
            'distinct_students' => $create + $link,
        ];
    }

    /**
     * L6 commit logic. Per valid row: `owed_minor > 0` creates exactly one Invoice
     * (source_system set, status Open, one InvoiceLine, offering_id null) and
     * `credit_minor > 0` creates exactly one WalletTransaction (kind Money, direction
     * Credit, reason LegacyCarryForward) against the student's wallet (created if
     * absent). Both may apply to the same row — handled independently. A legacy id
     * that no longer resolves to a linked user (e.g. the link was removed between
     * validate and commit) is skipped defensively rather than fatally erroring, since
     * validation already blocks this case for any row reaching here in the ordinary
     * flow.
     *
     * @return array{invoices_created: int, wallet_credits_created: int, noop: int, skip: int, distinct_students: int}
     */
    private function applyBalanceRows(ImportBatch $batch, bool $persist, ?User $actor = null): array
    {
        $invoicesCreated = 0;
        $walletCreditsCreated = 0;
        $noop = 0;
        $skip = 0;
        $studentIds = [];

        /** @var Collection<int, ImportRow> $rows */
        $rows = ImportRow::query()->where('batch_id', $batch->id)
            ->whereIn('status', [ImportRowStatus::Valid, ImportRowStatus::Warn])
            ->orderBy('row_number')
            ->get();

        foreach ($rows as $row) {
            $normalized = $row->normalized ?? [];
            $legacyId = $normalized['legacy_id'] ?? null;
            $currencyCode = $normalized['currency'] ?? null;
            $owed = (int) ($normalized['owed_minor'] ?? 0);
            $credit = (int) ($normalized['credit_minor'] ?? 0);

            if ($owed <= 0 && $credit <= 0) {
                $noop++;
                if ($persist) {
                    $row->update(['action' => ImportRowAction::Noop, 'status' => ImportRowStatus::Applied]);
                }

                continue;
            }

            $link = ImportLink::query()
                ->where('source_id', $batch->source_id)
                ->where('entity_type', 'user')
                ->where('legacy_id', $legacyId)
                ->first();

            $student = $link !== null ? User::query()->find($link->target_id) : null;
            if ($student === null || ! is_string($currencyCode) || Currency::tryFrom($currencyCode) === null) {
                $skip++;

                continue;
            }

            $studentIds[$student->id] = true;
            $currency = Currency::from($currencyCode);
            $target = null;

            if ($owed > 0) {
                $invoicesCreated++;
                if ($persist) {
                    $invoice = $this->createLegacyInvoice($batch, $student, $currency, $owed);
                    $target = ['type' => Invoice::class, 'id' => $invoice->id];
                }
            }

            if ($credit > 0) {
                $walletCreditsCreated++;
                if ($persist) {
                    $note = sprintf(
                        'Legacy balance carried forward from %s as of %s',
                        $batch->source->name,
                        $batch->control_totals['as_of'] ?? now()->toDateString(),
                    );
                    $tx = $this->wallets->credit(
                        $student,
                        $currency,
                        WalletKind::Money,
                        $credit,
                        LedgerReason::LegacyCarryForward,
                        actor: $actor,
                        note: $note,
                    );
                    $target ??= ['type' => \App\Models\WalletTransaction::class, 'id' => $tx->id];
                }
            }

            if ($persist) {
                $row->update([
                    'action' => ImportRowAction::Create,
                    'target_type' => $target['type'] ?? null,
                    'target_id' => $target['id'] ?? null,
                    'status' => ImportRowStatus::Applied,
                ]);
            }
        }

        return [
            'invoices_created' => $invoicesCreated,
            'wallet_credits_created' => $walletCreditsCreated,
            'noop' => $noop,
            'skip' => $skip,
            'distinct_students' => count($studentIds),
        ];
    }

    /**
     * One synthetic invoice, one line — D4. The description is plain factual data text
     * (source name + cutover date), matching how every other InvoiceLine description
     * in this app is written (see InvoiceService::createForEnrollment's
     * "{course code} — {course title}"): never a hardcoded English sentence baked in
     * as if it were the student's UI, just a record of what happened, in the language
     * this app's finance records have always been written in. The student-facing
     * transcript/statement screens are responsible for wrapping any UI copy around it.
     */
    private function createLegacyInvoice(ImportBatch $batch, User $student, Currency $currency, int $owedMinor): Invoice
    {
        $asOf = $batch->control_totals['as_of'] ?? now()->toDateString();

        $invoice = Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => $currency,
            'total_minor' => $owedMinor,
            'status' => InvoiceStatus::Open,
            'due_date' => null,
            'source_system' => $batch->source->code,
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'description' => sprintf('Balance carried forward from %s as of %s', $batch->source->name, $asOf),
            'offering_id' => null,
            'amount_minor' => $owedMinor,
        ]);

        return $invoice->fresh('lines');
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function createUser(ImportBatch $batch, array $normalized, string $legacyId): User
    {
        $email = $normalized['email'] ?? null;
        if ($email === null || $email === '') {
            $email = sprintf('legacy.%s.%s@no-email.invalid', mb_strtolower($batch->source->code), $legacyId);
        }

        $status = $batch->population === ImportPopulation::Active ? UserStatus::Pending : UserStatus::Archived;

        return User::query()->create([
            'email' => $email,
            'phone' => $normalized['phone'] ?? null,
            'first_name' => $normalized['first_name'] ?? '—',
            'last_name' => $normalized['last_name'] ?? '—',
            'password_hash' => null,
            'email_verified' => false,
            'preferred_locale' => $normalized['preferred_locale'] ?? 'en',
            'status' => $status,
            'country_code' => $normalized['country_code'] ?? null,
            'date_of_birth' => $normalized['date_of_birth'] ?? null,
            'source_system' => $batch->source->code,
        ]);
    }

    /**
     * Attaches a StudentProgram only when the mapped program_code matches a program
     * that already exists in the live catalog — the "exact match" half of D2. A code
     * with no match got a W_UNKNOWN_PROGRAM_CODE warning at validation and is skipped
     * here rather than silently creating a shadow program.
     *
     * @param  array<string, mixed>  $normalized
     */
    private function attachProgram(ImportBatch $batch, ?User $user, array $normalized): void
    {
        if ($user === null || empty($normalized['program_code'])) {
            return;
        }

        $program = Program::query()->where('code', $normalized['program_code'])->first();
        if ($program === null) {
            return;
        }

        StudentProgram::query()->firstOrCreate(
            ['student_id' => $user->id, 'program_id' => $program->id],
            [
                'status' => $batch->population === ImportPopulation::Active
                    ? StudentProgramStatus::Active
                    : StudentProgramStatus::Completed,
                'enrolled_at' => now(),
                'source_system' => $batch->source->code,
            ],
        );
    }

    /**
     * Reverses every CREATE this batch made. Refused once the batch is sealed, and
     * refused per-row when the created user is now referenced by native data (a role
     * assignment, an enrollment, a payment) — the rollback names exactly what blocks it
     * rather than failing vaguely.
     *
     * Scope note: only the STUDENT entity is reversible today. A BALANCE batch's
     * invoices and wallet credits are real financial records the moment they commit —
     * reversing them safely (crediting back a wallet that may already have been spent
     * from, voiding an invoice that may already carry a payment) is a distinct feature
     * with its own rules, not a copy of the STUDENT rollback's user-deletion logic, and
     * is out of scope for this phase.
     *
     * @return array{rolled_back: int, blocked: array<int, array{row: int, reason: string}>}
     */
    public function rollback(User $actor, ImportBatch $batch): array
    {
        if ($batch->status !== ImportBatchStatus::Committed) {
            throw new RuntimeException('Only a committed batch can be rolled back.');
        }
        if ($batch->isSealed()) {
            throw new RuntimeException('This batch was sealed on '.$batch->sealed_at->toDateString().' and can no longer be rolled back.');
        }
        if ($batch->entity_type !== ImportEntityType::Student) {
            throw new RuntimeException(__('import.rollback_unsupported_entity'));
        }

        $blocked = [];
        $rolledBack = 0;

        $this->audit->withAudit($actor, 'import.batch_rollback', function () use ($batch, $actor, &$blocked, &$rolledBack) {
            DB::transaction(function () use ($batch, $actor, &$blocked, &$rolledBack) {
                $rows = ImportRow::query()->where('batch_id', $batch->id)
                    ->where('status', ImportRowStatus::Applied)
                    ->where('action', ImportRowAction::Create)
                    ->get();

                foreach ($rows as $row) {
                    $user = $row->target_id !== null ? User::query()->find($row->target_id) : null;
                    if ($user === null) {
                        continue;
                    }

                    $reason = $this->blockingReasonFor($user);
                    if ($reason !== null) {
                        $blocked[] = ['row' => $row->row_number, 'reason' => $reason];

                        continue;
                    }

                    StudentProgram::query()->where('student_id', $user->id)->where('source_system', $batch->source->code)->delete();
                    ImportLink::query()->where('target_type', User::class)->where('target_id', $user->id)->delete();
                    $user->forceDelete();

                    $row->update(['status' => ImportRowStatus::RolledBack]);
                    $rolledBack++;
                }

                $batch->update([
                    'status' => ImportBatchStatus::RolledBack,
                    'rolled_back_by_id' => $actor->id,
                    'rolled_back_at' => now(),
                ]);
            });

            return $batch->fresh();
        }, entityType: ImportBatch::class);

        return ['rolled_back' => $rolledBack, 'blocked' => $blocked];
    }

    private function blockingReasonFor(User $user): ?string
    {
        if ($user->email_verified || $user->password_hash !== null) {
            return 'This person has already claimed their portal account.';
        }
        if ($user->roles()->exists()) {
            return 'A role has been assigned since import.';
        }
        if (DB::table('enrollments')->where('student_id', $user->id)->exists()) {
            return 'An enrollment now references this person.';
        }
        if (DB::table('payments')->where('student_id', $user->id)->exists()
            || DB::table('invoices')->where('student_id', $user->id)->exists()) {
            return 'A financial record now references this person.';
        }

        return null;
    }
}
