<?php

namespace App\Services\Import;

use App\Enums\CredentialType;
use App\Enums\Currency;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\ImportAccountClaimStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportEntityType;
use App\Enums\ImportMergeCandidateStatus;
use App\Enums\ImportPopulation;
use App\Enums\ImportRowAction;
use App\Enums\ImportRowStatus;
use App\Enums\ImportSourceKind;
use App\Enums\InvoiceStatus;
use App\Enums\LedgerReason;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\StudentProgramStatus;
use App\Enums\UserStatus;
use App\Enums\WalletKind;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Credential;
use App\Models\Enrollment;
use App\Models\ImportAccountClaim;
use App\Models\ImportBatch;
use App\Models\ImportGradeMapping;
use App\Models\ImportLink;
use App\Models\ImportMergeCandidate;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Finance\WalletService;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates the whole batch pipeline: upload -> profile -> map -> validate -> dry
 * run -> commit -> (rollback). See docs/legacy-data-import-plan.md §10.
 *
 * Three entity types are implemented:
 * - STUDENT (L0/L1/L2/L5): identity + program linkage, resolved via the full matching
 *   ladder from §6 — 1) an `import_links` hit on this source, 2) the Canvas/SIS
 *   crosswalk, 3) exact normalised email, 4) exact unambiguous student number,
 *   5) a unique (first, last, date_of_birth) triple, 6) the human merge queue
 *   (`import_merge_candidates`) for anything left unresolved. A `program_code`
 *   mapping links only to a program that already exists in the live catalog (the
 *   "exact code match" half of D2) and never creates a shadow program. Every row that
 *   results in a still-PENDING ACTIVE-population user is queued for an account-claim
 *   invitation (L5) — see §9.
 * - COURSE_RESULT (L3/L4): course results against the shadow catalog — §7. Every row
 *   must reference a student already linked by a committed STUDENT batch. Posts an
 *   Enrollment + AcademicRecord, find-or-creating a shadow Course/CourseOffering when
 *   no live one matches exactly. `counts_toward_gpa` is forced false on every imported
 *   record regardless of the grade mapping, so a legacy result never moves a student's
 *   live GPA on import — a registrar promotes individual records to transfer credit
 *   one at a time via `GradebookService`.
 * - BALANCE (L6): finance opening balances. One carried-forward invoice (owed) and/or
 *   one wallet credit (in hand) per student per currency, gated by an exact
 *   control-total match with no acknowledge override — see §8.
 * - CREDENTIAL (L8): a legacy diploma/certificate row imported as a historical
 *   `Credential` — §22.1. The source's own serial is kept verbatim in the existing
 *   `serial` column and never passes through `CredentialService::nextSerial()`, so it
 *   can never collide with or consume from SPIMS's own issuance counter.
 *   `regenerate()` refuses a legacy-sourced credential outright, and `/verify` renders
 *   it as a historical record rather than a SPIMS-issued one.
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
            ImportEntityType::CourseResult => $this->validateCourseResult($batch),
            ImportEntityType::Balance => $this->validateBalanceRows($batch),
            ImportEntityType::Credential => $this->validateCredentialRows($batch),
            default => $this->validateStudentRows($batch),
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
     * COURSE_RESULT validation: legacy_id must already resolve via an existing
     * import_links user row (E_UNKNOWN_STUDENT), legacy_letter must resolve via this
     * source's ImportGradeMapping table (E_UNMAPPED_GRADE), and a credit-hours mismatch
     * against an exact-matching live course is a warning, not an error
     * (W_CREDIT_HOURS_DIFFER) — see docs/legacy-data-import-plan.md §7, §14 Q6.
     */
    private function validateCourseResult(ImportBatch $batch): ImportBatch
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

                    if (! isset($normalized[$m['target_field']]) || $normalized[$m['target_field']] === null) {
                        $normalized[$m['target_field']] = $result['value'];
                    }
                }

                foreach (ImportCourseResultFields::requiredFor() as $required) {
                    if (empty($normalized[$required])) {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_REQUIRED_FIELD_MISSING',
                            'field' => $required,
                            'params' => ['field' => $required],
                        ];
                    }
                }

                $legacyId = $normalized['legacy_id'] ?? null;
                $courseCode = $normalized['course_code'] ?? null;
                $term = $normalized['term'] ?? null;

                // The natural key for a course result is per (student, course, term) —
                // one legacy student legitimately appears on many rows.
                $naturalKey = ($legacyId !== null && $legacyId !== '' && $courseCode !== null && $term !== null)
                    ? $legacyId.'|'.$courseCode.'|'.$term
                    : 'row-'.($i + 1);
                if (isset($seenKeys[$naturalKey])) {
                    $rowMessages[] = [
                        'level' => 'error',
                        'code' => 'E_DUPLICATE_NATURAL_KEY',
                        'field' => 'legacy_id',
                        'params' => ['legacy_id' => (string) $naturalKey],
                    ];
                    $naturalKey = $naturalKey.'#dup-'.($i + 1);
                }
                $seenKeys[$naturalKey] = true;

                if ($legacyId !== null && $legacyId !== '') {
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
                            'params' => ['legacy_id' => (string) $legacyId],
                        ];
                    }
                }

                $legacyLetter = $normalized['legacy_letter'] ?? null;
                if ($legacyLetter !== null && $legacyLetter !== '') {
                    $gradeMapping = ImportGradeMapping::query()
                        ->where('source_id', $batch->source_id)
                        ->where('legacy_letter', $legacyLetter)
                        ->first();
                    if ($gradeMapping === null) {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_UNMAPPED_GRADE',
                            'field' => 'legacy_letter',
                            'params' => ['legacy_letter' => (string) $legacyLetter],
                        ];
                    }
                }

                if (! empty($courseCode) && ! empty($normalized['credit_hours'])) {
                    $liveCourse = Course::query()->whereNull('source_system')->where('code', $courseCode)->first();
                    if ($liveCourse !== null && (int) $liveCourse->credit_hours !== (int) $normalized['credit_hours']) {
                        $rowMessages[] = [
                            'level' => 'warning',
                            'code' => 'W_CREDIT_HOURS_DIFFER',
                            'field' => 'credit_hours',
                            'params' => ['sheet' => (string) $normalized['credit_hours'], 'course' => (string) $liveCourse->credit_hours],
                        ];
                    }
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
     * L8 — legacy credentials (§22.1). Per row: legacy_id must already resolve via an
     * existing import_links user row (E_UNKNOWN_STUDENT, same as COURSE_RESULT and
     * BALANCE); credential_type must be one of the existing CredentialType values
     * (E_UNKNOWN_CREDENTIAL_TYPE); serial is required and, if it already belongs to a
     * different credential (native, or a different legacy source), is a hard error
     * (E_DUPLICATE_SERIAL) rather than letting the database's own unique constraint on
     * `credentials.serial` fail the commit — a serial already carried by *this* same
     * legacy source is not an error, since that is exactly what a corrected re-export
     * of the same record looks like (see applyCredentialRows()'s find-or-update).
     */
    private function validateCredentialRows(ImportBatch $batch): ImportBatch
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

                    if (! isset($normalized[$m['target_field']]) || $normalized[$m['target_field']] === null) {
                        $normalized[$m['target_field']] = $result['value'];
                    }
                }

                foreach (ImportCredentialFields::requiredFor() as $required) {
                    if (empty($normalized[$required])) {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_REQUIRED_FIELD_MISSING',
                            'field' => $required,
                            'params' => ['field' => $required],
                        ];
                    }
                }

                $legacyId = $normalized['legacy_id'] ?? null;
                $serial = isset($normalized['serial']) ? trim((string) $normalized['serial']) : null;
                $normalized['serial'] = $serial;

                $naturalKey = ($legacyId !== null && $legacyId !== '' && $serial !== null && $serial !== '')
                    ? $legacyId.'|'.$serial
                    : 'row-'.($i + 1);
                if (isset($seenKeys[$naturalKey])) {
                    $rowMessages[] = [
                        'level' => 'error',
                        'code' => 'E_DUPLICATE_NATURAL_KEY',
                        'field' => 'legacy_id',
                        'params' => ['legacy_id' => (string) $naturalKey],
                    ];
                    $naturalKey = $naturalKey.'#dup-'.($i + 1);
                }
                $seenKeys[$naturalKey] = true;

                if ($legacyId !== null && $legacyId !== '') {
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
                            'params' => ['legacy_id' => (string) $legacyId],
                        ];
                    }
                }

                $credentialType = isset($normalized['credential_type']) && $normalized['credential_type'] !== ''
                    ? CredentialType::tryFrom(strtoupper(trim((string) $normalized['credential_type'])))
                    : null;
                if (! empty($normalized['credential_type']) && $credentialType === null) {
                    $rowMessages[] = [
                        'level' => 'error',
                        'code' => 'E_UNKNOWN_CREDENTIAL_TYPE',
                        'field' => 'credential_type',
                        'params' => ['credential_type' => (string) $normalized['credential_type']],
                    ];
                }

                if ($serial !== null && $serial !== '') {
                    $existingCredential = Credential::query()->where('serial', $serial)->first();
                    if ($existingCredential !== null && $existingCredential->source_system !== $batch->source->code) {
                        $rowMessages[] = [
                            'level' => 'error',
                            'code' => 'E_DUPLICATE_SERIAL',
                            'field' => 'serial',
                            'params' => ['serial' => $serial],
                        ];
                    }
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
            ImportEntityType::Balance => $this->balanceControlTotals($batch),
            default => $this->defaultControlTotals($batch),
        };
    }

    /**
     * The plain "rows in file vs. rows that would import" count, shared by STUDENT and
     * COURSE_RESULT — neither needs a richer control total than a row count, unlike
     * BALANCE's per-currency money gate below.
     *
     * @return array{declared_rows: int, computed_rows: int, distinct_students: int}
     */
    private function defaultControlTotals(ImportBatch $batch): array
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

        // "Import Populi first, then Canvas" — rung 2 of the matching ladder (the
        // SIS User ID crosswalk) only works once the SIS source's own import_links
        // rows exist, so an LMS-kind STUDENT batch is refused until at least one
        // STUDENT batch from a SIS-kind source has committed. See §6.
        if ($batch->entity_type === ImportEntityType::Student && $batch->source->kind === ImportSourceKind::Lms) {
            $sisCommitted = ImportBatch::query()
                ->where('entity_type', ImportEntityType::Student)
                ->where('status', ImportBatchStatus::Committed)
                ->whereHas('source', fn ($q) => $q->where('kind', ImportSourceKind::Sis))
                ->exists();

            if (! $sisCommitted) {
                throw new RuntimeException(__('import.error_code.E_CANVAS_BEFORE_POPULI'));
            }
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
                $this->queueAccountClaims($batch);
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
     * against zero, so real money in an undeclared currency still fails loudly rather
     * than committing silently.
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
            ImportEntityType::CourseResult => $this->applyCourseResultRows($batch, $persist),
            ImportEntityType::Balance => $this->applyBalanceRows($batch, $persist, $actor),
            ImportEntityType::Credential => $this->applyCredentialRows($batch, $persist),
            default => $this->applyStudentRows($batch, $persist),
        };
    }

    /**
     * Row-level identity resolution walks the full matching ladder from §6 of the plan:
     *
     *   1. `import_links` hit on (this source, legacy_id) — same person, link.
     *   2. Canvas/SIS crosswalk — an LMS-kind source's legacy_id matches an
     *      import_links row for a *different* SIS-kind source — same person, link.
     *   3. Exact normalised email match against an existing user — link.
     *   4. Exact, unambiguous legacy student number match — link.
     *   5. Normalised (first, last, date_of_birth) triple, unique school-wide — link.
     *      Ambiguous (more than one match) never auto-links; falls through to 6.
     *   6. Anything else — never auto-linked. Queued in import_merge_candidates with a
     *      best-guess candidate when one can be found via a looser signal.
     *
     * Two *existing native* SPIMS users are never merged by this method — every rung
     * above only ever matches an incoming row against one existing `users` row.
     *
     * @return array{create: int, link: int, skip: int, queued: int, distinct_students: int}
     */
    private function applyStudentRows(ImportBatch $batch, bool $persist): array
    {
        $create = 0;
        $link = 0;
        $skip = 0;
        $queued = 0;

        // Rows created earlier in this same run must never be compared against later
        // rows by the loose rung-6 search: two different rows in one file are, by
        // construction, two different people (a genuine repeat shares a legacy_id and
        // is already an E_DUPLICATE_NATURAL_KEY error). Without this, two unrelated
        // siblings on the same sheet — sharing a surname is common — would flag each
        // other as a possible duplicate purely because they were imported together.
        $createdThisRun = [];

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

            // Rung 1: import_links hit on this exact source.
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

            // Rung 2: the Canvas/SIS crosswalk. Only applies to an LMS-kind source's
            // rows; the "legacy_id" of a Canvas row is its SIS User ID, which *is* the
            // Populi person id in a competently configured integration.
            if ($batch->source->kind === ImportSourceKind::Lms) {
                $crosswalk = ImportLink::query()
                    ->where('entity_type', 'user')
                    ->where('legacy_id', $legacyId)
                    ->where('source_id', '!=', $batch->source_id)
                    ->whereHas('source', fn ($q) => $q->where('kind', ImportSourceKind::Sis))
                    ->first();

                if ($crosswalk !== null) {
                    $link++;
                    if ($persist) {
                        $user = User::query()->find($crosswalk->target_id);
                        $this->linkUser($batch, $legacyId, $user);
                        $this->attachProgram($batch, $user, $normalized);
                        $row->update(['action' => ImportRowAction::Link, 'target_type' => User::class, 'target_id' => $user?->id, 'status' => ImportRowStatus::Applied]);
                    }

                    continue;
                }
            }

            // Rung 3: exact normalised email match.
            $email = $normalized['email'] ?? null;
            $matchedUser = $email !== null && $email !== ''
                ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first()
                : null;

            // An "escalation" is a genuine ambiguity signal picked up along the ladder —
            // more than one exact match on a rung that is supposed to be unambiguous.
            // It always carries a candidate (the ambiguous set is never empty) and is
            // never overwritten once set, since the earliest concrete signal is the one
            // worth showing a human. A row with *no* signal at all — the common case for
            // a person nobody in SPIMS has ever heard of — is not an escalation and, if
            // the looser search below finds nothing either, is simply created.
            $escalation = null;

            // Rung 4: exact, unambiguous legacy student number match.
            if ($matchedUser === null) {
                $studentNumber = $normalized['student_number'] ?? null;
                if ($studentNumber !== null && $studentNumber !== '') {
                    $numberMatches = User::query()->where('student_number', $studentNumber)->get();
                    if ($numberMatches->count() === 1) {
                        $matchedUser = $numberMatches->first();
                    } elseif ($numberMatches->count() > 1) {
                        $escalation = [$numberMatches->first(), ['student_number'], 1.0];
                    }
                }
            }

            // Rung 5: normalised (first, last, date_of_birth) triple, unique school-wide.
            if ($matchedUser === null) {
                $dob = $normalized['date_of_birth'] ?? null;
                $first = $normalized['first_name'] ?? null;
                $last = $normalized['last_name'] ?? null;

                if ($dob !== null && $dob !== '' && $first && $last) {
                    $normFirst = $this->normalizeNameForMatch($first);
                    $normLast = $this->normalizeNameForMatch($last);

                    $dobMatches = User::query()->whereDate('date_of_birth', $dob)->get()
                        ->filter(fn (User $u) => $this->normalizeNameForMatch((string) $u->first_name) === $normFirst
                            && $this->normalizeNameForMatch((string) $u->last_name) === $normLast);

                    if ($dobMatches->count() === 1) {
                        $matchedUser = $dobMatches->first();
                    } elseif ($dobMatches->count() > 1 && $escalation === null) {
                        $escalation = [$dobMatches->first(), ['name', 'date_of_birth'], 1.0];
                    }
                }
            }

            if ($matchedUser !== null) {
                $link++;
                if ($persist) {
                    $this->linkUser($batch, $legacyId, $matchedUser);
                    $this->attachProgram($batch, $matchedUser, $normalized);
                    $row->update(['action' => ImportRowAction::Link, 'target_type' => User::class, 'target_id' => $matchedUser->id, 'status' => ImportRowStatus::Applied]);
                }

                continue;
            }

            // Rung 6: the human merge queue. An explicit escalation from rung 4/5 above
            // always queues; otherwise a looser signal (an exact name match without a
            // usable DOB, or name similarity) is tried, and only a row with genuinely
            // no resemblance to anyone at all — the ordinary case for a first-time
            // import — falls through to a plain create, exactly as before L2.
            [$candidateUser, $matchedOn, $score] = $escalation ?? $this->bestGuessCandidate($normalized, $createdThisRun);

            if ($escalation !== null || $candidateUser !== null) {
                $queued++;
                if ($persist) {
                    ImportMergeCandidate::query()->create([
                        'source_id' => $batch->source_id,
                        'legacy_id' => $legacyId,
                        'batch_id' => $batch->id,
                        'candidate_user_id' => $candidateUser?->id,
                        'score' => $score,
                        'matched_on' => $matchedOn,
                        'payload_preview' => $row->payload,
                        'status' => ImportMergeCandidateStatus::Pending,
                    ]);
                    $row->update(['action' => ImportRowAction::Queued]);
                }

                continue;
            }

            $create++;
            if ($persist) {
                $user = $this->createUser($batch, $normalized, $legacyId);
                $createdThisRun[] = $user->id;
                $this->linkUser($batch, $legacyId, $user);
                $this->attachProgram($batch, $user, $normalized);
                $row->update(['action' => ImportRowAction::Create, 'target_type' => User::class, 'target_id' => $user->id, 'status' => ImportRowStatus::Applied]);
            }
        }

        return [
            'create' => $create,
            'link' => $link,
            'skip' => $skip,
            'queued' => $queued,
            'distinct_students' => $create + $link,
        ];
    }

    /**
     * The looser signal tried once the deterministic ladder (rungs 1-5) and any genuine
     * ambiguity there have both come up empty. Tries, in order: an exact normalised-name
     * match without a usable DOB; then PHP's `similar_text` name similarity over
     * existing users. Never invents a match with no signal at all — a null candidate
     * (nothing above the similarity floor) tells the caller this row has no resemblance
     * to anyone and should simply be created.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<int, string>  $excludeUserIds  users created earlier in this same
     *                                              run — never a candidate for a later
     *                                              row in the same file (see applyRows)
     * @return array{0: ?User, 1: array<int, string>, 2: float}
     */
    private function bestGuessCandidate(array $normalized, array $excludeUserIds = []): array
    {
        $first = (string) ($normalized['first_name'] ?? '');
        $last = (string) ($normalized['last_name'] ?? '');
        if ($first === '' || $last === '') {
            return [null, [], 0.0];
        }

        $normFirst = $this->normalizeNameForMatch($first);
        $normLast = $this->normalizeNameForMatch($last);
        $pool = User::query()->whereNotIn('id', $excludeUserIds)->get(['id', 'first_name', 'last_name']);

        $nameMatches = $pool->filter(fn (User $u) => $this->normalizeNameForMatch((string) $u->first_name) === $normFirst
            && $this->normalizeNameForMatch((string) $u->last_name) === $normLast);

        if ($nameMatches->count() >= 1) {
            return [$nameMatches->first(), ['name'], 0.9];
        }

        $needle = $normFirst.' '.$normLast;
        $best = null;
        $bestPercent = 0.0;

        foreach ($pool as $candidate) {
            $haystack = $this->normalizeNameForMatch((string) $candidate->first_name).' '.$this->normalizeNameForMatch((string) $candidate->last_name);
            similar_text($needle, $haystack, $percent);
            if ($percent > $bestPercent) {
                $bestPercent = $percent;
                $best = $candidate;
            }
        }

        // 60% let unrelated short names collide by chance ("Karim Saad" vs "Mariam
        // Samuel" scores 61%) — a bulk import of thirty genuinely distinct people found
        // pairs above 60% with room to spare, up to 64%. 80% still catches what this
        // signal exists for — a typo or a transliteration variant of the same name —
        // without flagging ordinary unrelated people for human review.
        if ($best !== null && $bestPercent >= 80.0) {
            return [$best, ['name_similarity'], round($bestPercent / 100, 2)];
        }

        return [null, [], 0.0];
    }

    /**
     * Normalises a name for identity matching only — never for display or storage.
     * Trims, lowercases, and strips Arabic tashkeel/alef/ta-marbuta variants (never
     * transliterates — see §6). Applied identically to incoming rows and stored users
     * so the comparison is fair regardless of which side is Arabic.
     */
    private function normalizeNameForMatch(string $value): string
    {
        return $this->transforms->normalizeArabic(mb_strtolower(trim($value)));
    }

    /**
     * Creates the permanent import_links row for this source + legacy id pointing at
     * an already-existing user (rungs 2-5) — shared by applyRows() and the merge queue
     * resolution below, so a future re-import of the same legacy id hits rung 1.
     */
    private function linkUser(ImportBatch $batch, string $legacyId, ?User $user): void
    {
        if ($user === null) {
            return;
        }

        ImportLink::query()->create([
            'source_id' => $batch->source_id,
            'entity_type' => 'user',
            'legacy_id' => $legacyId,
            'target_type' => User::class,
            'target_id' => $user->id,
            'first_batch_id' => $batch->id,
            'last_batch_id' => $batch->id,
        ]);
    }

    /**
     * Resolves one merge-queue row (rung 6). `merge` links to the stored candidate
     * exactly as rungs 1-5 would have; `reject` creates a brand-new user exactly as an
     * unresolved row would have; `skip` leaves it PENDING for later. Every decision is
     * audited. See docs/legacy-data-import-plan.md §11.10.
     */
    public function resolveMergeCandidate(User $actor, ImportMergeCandidate $candidate, string $decision): ImportMergeCandidate
    {
        if ($candidate->status !== ImportMergeCandidateStatus::Pending) {
            throw new RuntimeException('This merge candidate has already been resolved.');
        }
        if (! in_array($decision, ['merge', 'reject', 'skip'], true)) {
            throw new RuntimeException('Unknown merge decision.');
        }
        if ($decision === 'merge' && $candidate->candidate_user_id === null) {
            throw new RuntimeException('There is no candidate to merge with — reject and create a new person, or skip.');
        }

        return $this->audit->withAudit($actor, 'import.merge_resolve', function () use ($candidate, $actor, $decision) {
            return DB::transaction(function () use ($candidate, $actor, $decision) {
                $batch = $candidate->batch;
                $row = ImportRow::query()->where('batch_id', $candidate->batch_id)->where('natural_key', $candidate->legacy_id)->first();
                if ($row === null) {
                    throw new RuntimeException('The original import row for this merge candidate could not be found.');
                }
                $normalized = $row->normalized ?? [];

                if ($decision === 'merge') {
                    $user = $candidate->candidateUser;
                    $this->linkUser($batch, $candidate->legacy_id, $user);
                    $this->attachProgram($batch, $user, $normalized);
                    $row->update(['action' => ImportRowAction::Link, 'target_type' => User::class, 'target_id' => $user?->id, 'status' => ImportRowStatus::Applied]);
                    $candidate->update(['status' => ImportMergeCandidateStatus::Merged, 'resolved_by_id' => $actor->id, 'resolved_at' => now()]);
                } elseif ($decision === 'reject') {
                    $user = $this->createUser($batch, $normalized, $candidate->legacy_id);
                    $this->linkUser($batch, $candidate->legacy_id, $user);
                    $this->attachProgram($batch, $user, $normalized);
                    $row->update(['action' => ImportRowAction::Create, 'target_type' => User::class, 'target_id' => $user->id, 'status' => ImportRowStatus::Applied]);
                    $candidate->update(['status' => ImportMergeCandidateStatus::NewUser, 'resolved_by_id' => $actor->id, 'resolved_at' => now()]);
                }
                // 'skip' leaves the candidate PENDING — still audited, nothing else changes.

                return $candidate->fresh();
            });
        }, entityType: ImportMergeCandidate::class);
    }

    /**
     * The COURSE_RESULT commit path — §7. For each valid row: resolve the student via
     * the existing import_links row (validate() already blocked any row that can't),
     * reuse a live course on an exact code match or find-or-create a shadow one,
     * find-or-create a shadow CourseOffering for (course, term), and create/update the
     * Enrollment + AcademicRecord. `counts_toward_gpa` is forced false on every row
     * regardless of the grade mapping — the plan's explicit instruction — so no GPA is
     * touched here; a registrar promotes individual records later via
     * GradebookService::promoteToTransferCredit().
     *
     * @return array<string, int>
     */
    private function applyCourseResultRows(ImportBatch $batch, bool $persist): array
    {
        $created = 0;
        $updated = 0;
        $skip = 0;
        $shadowCoursesCreated = 0;
        $shadowOfferingsCreated = 0;

        /** @var Collection<int, ImportRow> $rows */
        $rows = ImportRow::query()->where('batch_id', $batch->id)
            ->whereIn('status', [ImportRowStatus::Valid, ImportRowStatus::Warn])
            ->orderBy('row_number')
            ->get();

        foreach ($rows as $row) {
            $normalized = $row->normalized ?? [];
            $legacyId = $normalized['legacy_id'] ?? null;

            $link = $legacyId !== null
                ? ImportLink::query()
                    ->where('source_id', $batch->source_id)
                    ->where('entity_type', 'user')
                    ->where('legacy_id', $legacyId)
                    ->first()
                : null;

            if ($link === null) {
                $skip++;

                continue;
            }

            if (! $persist) {
                $created++;

                continue;
            }

            $student = User::query()->find($link->target_id);
            if ($student === null) {
                $skip++;

                continue;
            }

            $gradeMapping = ImportGradeMapping::query()
                ->where('source_id', $batch->source_id)
                ->where('legacy_letter', $normalized['legacy_letter'])
                ->first();
            if ($gradeMapping === null) {
                // validate() would already have blocked this row with E_UNMAPPED_GRADE.
                $skip++;

                continue;
            }

            $courseCode = (string) $normalized['course_code'];
            $course = Course::query()->where('code', $courseCode)->first();
            if ($course === null) {
                $course = Course::query()->create([
                    'code' => $courseCode,
                    'title' => $normalized['course_title'] ?? $courseCode,
                    'credit_hours' => (int) $normalized['credit_hours'],
                    'is_standalone' => false,
                    // Hidden from the public catalog regardless of this flag — the
                    // catalog, prerequisites and interest-flag surfaces all filter on
                    // `active = true`, and a shadow course never is. See
                    // ImportCatalogIsolationTest.
                    'active' => false,
                    'source_system' => $batch->source->code,
                ]);
                $shadowCoursesCreated++;
            }

            $term = (string) $normalized['term'];
            $offering = CourseOffering::query()
                ->where('course_id', $course->id)
                ->where('legacy_term', $term)
                ->whereNotNull('source_system')
                ->first();
            if ($offering === null) {
                $offering = CourseOffering::query()->create([
                    'course_id' => $course->id,
                    'semester_id' => null,
                    'mode' => OfferingMode::SelfPaced,
                    'status' => OfferingStatus::Archived,
                    'source_system' => $batch->source->code,
                    'legacy_term' => $term,
                ]);
                $shadowOfferingsCreated++;
            }

            $isWithdrawal = $gradeMapping->spims_letter === 'W' || in_array(strtoupper((string) $normalized['legacy_letter']), ['W', 'WD'], true);

            $studentProgram = null;
            if (! empty($normalized['program_code'])) {
                $program = Program::query()->where('code', $normalized['program_code'])->first();
                if ($program !== null) {
                    $studentProgram = StudentProgram::query()
                        ->where('student_id', $student->id)
                        ->where('program_id', $program->id)
                        ->first();
                }
            }

            $percent = $normalized['legacy_percent'] ?? null;
            if ($percent === null) {
                // academic_records.percent is NOT NULL; fall back to the grade
                // mapping's own percent band when the source gave no percent at all.
                $percent = $gradeMapping->min_percent !== null && $gradeMapping->max_percent !== null
                    ? round(($gradeMapping->min_percent + $gradeMapping->max_percent) / 2, 2)
                    : 0.0;
            }

            $enrollment = Enrollment::query()->updateOrCreate(
                ['student_id' => $student->id, 'offering_id' => $offering->id],
                [
                    'student_program_id' => $studentProgram?->id,
                    'status' => $isWithdrawal ? EnrollmentStatus::Withdrawn : EnrollmentStatus::Completed,
                    'is_audit' => false,
                    'grade_type' => $isWithdrawal ? GradeType::Withdrawal : GradeType::Standard,
                    'final_percent' => (float) $percent,
                    'final_letter' => $gradeMapping->spims_letter,
                    'final_gpa_points' => $gradeMapping->gpa_points,
                    'grade_status' => GradeStatus::Locked,
                    'progress_percent' => 100,
                    'source_system' => $batch->source->code,
                ],
            );

            $existedBefore = AcademicRecord::query()->where('enrollment_id', $enrollment->id)->exists();

            $record = AcademicRecord::query()->updateOrCreate(
                ['enrollment_id' => $enrollment->id],
                [
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'letter_grade' => $gradeMapping->spims_letter,
                    'percent' => (float) $percent,
                    'gpa_points' => $gradeMapping->gpa_points,
                    'credit_hours' => (int) $normalized['credit_hours'],
                    'term' => $term,
                    'is_passing' => $gradeMapping->is_passing,
                    // Forced false regardless of the grade mapping's own value — every
                    // legacy row at import time, per plan §7. A registrar promotes one
                    // record at a time via GradebookService::promoteToTransferCredit().
                    'counts_toward_gpa' => false,
                    'source_system' => $batch->source->code,
                ],
            );

            if ($studentProgram !== null) {
                $programCourse = ProgramCourse::query()
                    ->where('program_id', $studentProgram->program_id)
                    ->where('course_id', $course->id)
                    ->first();
                if ($programCourse !== null) {
                    ProgramRequirementFulfillment::query()->updateOrCreate(
                        ['student_program_id' => $studentProgram->id, 'program_course_id' => $programCourse->id],
                        ['academic_record_id' => $record->id, 'applied_at' => now()],
                    );
                }
            }

            if ($existedBefore) {
                $updated++;
            } else {
                $created++;
            }

            $row->update([
                'action' => $existedBefore ? ImportRowAction::Update : ImportRowAction::Create,
                'target_type' => AcademicRecord::class,
                'target_id' => $record->id,
                'status' => ImportRowStatus::Applied,
            ]);
        }

        return [
            'create' => $created,
            'link' => $updated,
            'skip' => $skip,
            'distinct_students' => $created + $updated,
            'shadow_courses_created' => $shadowCoursesCreated,
            'shadow_offerings_created' => $shadowOfferingsCreated,
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
     * L8 commit logic (§22.1). Per valid row: resolve the student via the existing
     * import_links row (validate() already blocked any row that can't), resolve the
     * credential type and, optionally, an exact-match live program, and write one
     * `Credential` row with `source_system` set and the source's own serial kept
     * verbatim in the existing `serial` column — never through
     * `CredentialService::nextSerial()`, which stays reserved for SPIMS's own
     * issuance. A serial already carried by *this* source's Credential is updated in
     * place rather than duplicated, so re-importing a corrected export is idempotent
     * exactly like every other entity type (D6).
     *
     * @return array{create: int, link: int, skip: int, distinct_students: int}
     */
    private function applyCredentialRows(ImportBatch $batch, bool $persist): array
    {
        $created = 0;
        $updated = 0;
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
            $serial = $normalized['serial'] ?? null;

            $link = $legacyId !== null
                ? ImportLink::query()
                    ->where('source_id', $batch->source_id)
                    ->where('entity_type', 'user')
                    ->where('legacy_id', $legacyId)
                    ->first()
                : null;

            $credentialType = CredentialType::tryFrom(strtoupper(trim((string) ($normalized['credential_type'] ?? ''))));

            if ($link === null || $serial === null || $serial === '' || $credentialType === null) {
                // validate() would already have blocked this row.
                $skip++;

                continue;
            }

            if (! $persist) {
                $created++;

                continue;
            }

            $student = User::query()->find($link->target_id);
            if ($student === null) {
                $skip++;

                continue;
            }

            $studentIds[$student->id] = true;

            $programId = null;
            if (! empty($normalized['program_code'])) {
                $programId = Program::query()->where('code', $normalized['program_code'])->value('id');
            }

            $issuedAt = $normalized['issued_at'] ?? null;
            $language = ! empty($normalized['language']) ? (string) $normalized['language'] : 'en';

            $attributes = [
                'student_id' => $student->id,
                'type' => $credentialType,
                'program_id' => $programId,
                'offering_id' => null,
                'language' => $language,
                'signatory_name' => $normalized['signatory_name'] ?? null,
                'signatory_title' => $normalized['signatory_title'] ?? null,
                'issued_at' => $issuedAt,
                'source_system' => $batch->source->code,
            ];

            // A serial already belonging to *this* source's own Credential is a
            // corrected re-export of the same record — update in place rather than
            // duplicating (D6). validate() already refused a serial collision with any
            // credential from a different source or a native one.
            $existing = Credential::query()->where('serial', $serial)->first();

            if ($existing !== null) {
                $existing->update($attributes);
                $target = $existing;
                $updated++;
            } else {
                $target = Credential::query()->create($attributes + [
                    'serial' => $serial,
                    'qr_token' => (string) Str::ulid(),
                ]);
                $created++;
            }

            $row->update([
                'action' => $existing !== null ? ImportRowAction::Update : ImportRowAction::Create,
                'target_type' => Credential::class,
                'target_id' => $target->id,
                'status' => ImportRowStatus::Applied,
            ]);
        }

        return [
            'create' => $created,
            'link' => $updated,
            'skip' => $skip,
            'distinct_students' => count($studentIds),
        ];
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
            'student_number' => $normalized['student_number'] ?? null,
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
     * L5 — queues the active-student account-claim cohort. See
     * docs/legacy-data-import-plan.md §9: every row that resulted in a still-PENDING
     * user (freshly created, or linked to a user left PENDING by an earlier batch)
     * gets an import_account_claims row so a registrar can review the headcount and
     * send invitations later — this method never sends mail itself, it only queues.
     * A user who is already ACTIVE, ARCHIVED or Suspended is never touched: the
     * `status = Pending` filter below is exactly that guard. A no-op for any
     * non-STUDENT entity type or a non-ACTIVE population.
     *
     * Reads import_rows written by applyRows() moments ago in the same transaction
     * rather than changing applyRows()'s own return value or side effects, so the
     * existing ImportBatchServiceTest suite is untouched by this addition.
     */
    private function queueAccountClaims(ImportBatch $batch): void
    {
        if ($batch->entity_type !== ImportEntityType::Student || $batch->population !== ImportPopulation::Active) {
            return;
        }

        $userIds = ImportRow::query()->where('batch_id', $batch->id)
            ->where('target_type', User::class)
            ->whereIn('action', [ImportRowAction::Create, ImportRowAction::Link])
            ->where('status', ImportRowStatus::Applied)
            ->pluck('target_id')
            ->filter()
            ->unique();

        if ($userIds->isEmpty()) {
            return;
        }

        $eligibleUserIds = User::query()
            ->whereIn('id', $userIds)
            ->where('status', UserStatus::Pending)
            ->pluck('id');

        foreach ($eligibleUserIds as $userId) {
            // firstOrCreate, not updateOrCreate: a claim already SENT/BOUNCED/CLAIMED
            // by an earlier batch keeps its own history and is never reset to QUEUED
            // just because a later batch happens to reference the same person.
            ImportAccountClaim::query()->firstOrCreate(
                ['user_id' => $userId],
                ['batch_id' => $batch->id, 'status' => ImportAccountClaimStatus::Queued],
            );
        }
    }

    /**
     * Reverses every CREATE this batch made. Refused once the batch is sealed, and
     * refused per-row when the created record is now referenced by native data (a role
     * assignment, an enrollment, a payment — for STUDENT; a promotion to transfer
     * credit — for COURSE_RESULT; a revocation — for CREDENTIAL) — the rollback names
     * exactly what blocks it rather than failing vaguely.
     *
     * Scope note: STUDENT, COURSE_RESULT and CREDENTIAL are reversible today. A
     * CREDENTIAL row stays reversible for the same window as STUDENT (until the batch
     * seals) rather than being blocked the moment `/verify` is viewed or the file is
     * downloaded — a historical record has no downstream financial/enrollment
     * dependents the way a STUDENT or COURSE_RESULT row can, so the only "native use"
     * that should block it is an actual state change: the credential being revoked.
     * See §22.2. A BALANCE batch's invoices and wallet credits are real financial
     * records the moment they commit — reversing them safely (crediting back a wallet
     * that may already have been spent from, voiding an invoice that may already carry
     * a payment) is a distinct feature with its own rules, not a copy of the STUDENT
     * rollback's delete-the-row logic, and is out of scope for this phase.
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
        if ($batch->entity_type === ImportEntityType::Balance) {
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
                    $reason = match ($batch->entity_type) {
                        ImportEntityType::CourseResult => $this->rollbackCourseResultRow($batch, $row),
                        ImportEntityType::Credential => $this->rollbackCredentialRow($batch, $row),
                        default => $this->rollbackStudentRow($batch, $row),
                    };

                    if ($reason === false) {
                        // Nothing to reverse (target already gone) — leave the row as is.
                        continue;
                    }

                    if (is_string($reason)) {
                        $blocked[] = ['row' => $row->row_number, 'reason' => $reason];

                        continue;
                    }

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

    /**
     * @return string|false|null a blocking reason, `false` if there was nothing to
     *                           reverse, or `null` once successfully rolled back
     */
    private function rollbackStudentRow(ImportBatch $batch, ImportRow $row): string|false|null
    {
        $user = $row->target_id !== null ? User::query()->find($row->target_id) : null;
        if ($user === null) {
            return false;
        }

        $reason = $this->blockingReasonFor($user);
        if ($reason !== null) {
            return $reason;
        }

        StudentProgram::query()->where('student_id', $user->id)->where('source_system', $batch->source->code)->delete();
        ImportLink::query()->where('target_type', User::class)->where('target_id', $user->id)->delete();
        $user->forceDelete();

        return null;
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

    /**
     * Reverses one COURSE_RESULT row: deletes the AcademicRecord, its
     * ProgramRequirementFulfillment (if any) and the Enrollment it was posted against.
     * The shadow Course/CourseOffering it was posted on are left in place — they are
     * find-or-create idempotent infrastructure that other rows and other batches may
     * already be sharing, and leaving them behind is harmless (they stay invisible to
     * every native surface regardless). Refused once the record has been promoted to
     * transfer credit (D7) — that is native use of imported data, same rule as a
     * STUDENT row that has since been referenced elsewhere.
     *
     * @return string|false|null a blocking reason, `false` if there was nothing to
     *                           reverse, or `null` once successfully rolled back
     */
    private function rollbackCourseResultRow(ImportBatch $batch, ImportRow $row): string|false|null
    {
        $record = $row->target_id !== null ? AcademicRecord::query()->find($row->target_id) : null;
        if ($record === null) {
            return false;
        }

        if ($record->counts_toward_gpa) {
            return 'This record has been promoted to transfer credit and now counts toward GPA.';
        }

        ProgramRequirementFulfillment::query()->where('academic_record_id', $record->id)->delete();
        $enrollmentId = $record->enrollment_id;
        $record->delete();
        if ($enrollmentId !== null) {
            Enrollment::query()->where('id', $enrollmentId)->delete();
        }

        return null;
    }

    /**
     * Reverses one CREDENTIAL row (§22.2): deletes the Credential outright. Refused
     * once the credential has been revoked — a revocation is a deliberate state change
     * made through this system since the import, exactly the same "native use" rule
     * STUDENT and COURSE_RESULT rollback already apply to their own kind of downstream
     * reference. A stray or duplicate import that nobody has acted on yet is always
     * reversible until the batch seals, matching STUDENT's own rollback window — a
     * historical record has no enrollment or financial dependents to protect.
     *
     * @return string|false|null a blocking reason, `false` if there was nothing to
     *                           reverse, or `null` once successfully rolled back
     */
    private function rollbackCredentialRow(ImportBatch $batch, ImportRow $row): string|false|null
    {
        $credential = $row->target_id !== null ? Credential::query()->find($row->target_id) : null;
        if ($credential === null) {
            return false;
        }

        if ($credential->revoked_at !== null) {
            return 'This credential has since been revoked.';
        }

        $credential->delete();

        return null;
    }
}
