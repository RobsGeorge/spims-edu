<?php

namespace App\Services\Import;

use App\Enums\ImportAccountClaimStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportEntityType;
use App\Enums\ImportMergeCandidateStatus;
use App\Enums\ImportPopulation;
use App\Enums\ImportRowAction;
use App\Enums\ImportRowStatus;
use App\Enums\ImportSourceKind;
use App\Enums\StudentProgramStatus;
use App\Enums\UserStatus;
use App\Models\ImportAccountClaim;
use App\Models\ImportBatch;
use App\Models\ImportLink;
use App\Models\ImportMergeCandidate;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\Program;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates the whole batch pipeline for the STUDENT entity type:
 * upload -> profile -> map -> validate -> dry run -> commit -> (rollback).
 * See docs/legacy-data-import-plan.md §10.
 *
 * v1/L2 scope: identity + program linkage only — the full matching ladder (rungs 1-6,
 * §6) plus the human merge queue for anything the ladder cannot resolve
 * deterministically. Course results, finance and credentials are not yet implemented;
 * a `program_code` mapping links only to a program that already exists in the live
 * catalog (the "exact code match" half of D2) and never creates a shadow program.
 */
class ImportBatchService
{
    public function __construct(
        private readonly ImportFileReader $reader,
        private readonly ImportProfilerService $profiler,
        private readonly ImportMappingSuggestionService $suggester,
        private readonly ImportTransformService $transforms,
        private readonly ObjectStorageService $storage,
        private readonly AuditLogWriter $audit,
    ) {}

    public function createFromUpload(
        User $actor,
        ImportSource $source,
        UploadedFile $file,
        ImportPopulation $population,
        ?string $sheetName = null,
        int $headerRow = 1,
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
            'entity_type' => ImportEntityType::Student,
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
     * Re-reads the staged file, applies the mapping, and writes one ImportRow per data
     * row with validation messages. Nothing outside `import_rows` is written here.
     */
    public function validate(ImportBatch $batch): ImportBatch
    {
        $source = $batch->source;
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
     * Runs the full commit path inside a transaction that is always rolled back, and
     * reports what would happen. Nothing is written — verified by ImportDryRunTest,
     * which asserts the row counts are identical before and after.
     *
     * @return array{create: int, link: int, skip: int, distinct_students: int}
     */
    public function dryRun(ImportBatch $batch): array
    {
        $report = ['create' => 0, 'link' => 0, 'skip' => 0, 'distinct_students' => 0];

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
     * @return array{declared_rows: int, computed_rows: int, distinct_students: int}
     */
    private function controlTotals(ImportBatch $batch): array
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

    public function commit(User $actor, ImportBatch $batch): ImportBatch
    {
        if (! in_array($batch->status, [ImportBatchStatus::DryRun, ImportBatchStatus::Validated], true)) {
            throw new RuntimeException('Batch must be validated before it can be committed.');
        }

        // "Import Populi first, then Canvas" — rung 2 of the matching ladder (the
        // SIS User ID crosswalk) only works once the SIS source's own import_links
        // rows exist, so an LMS-kind batch is refused until at least one STUDENT
        // batch from a SIS-kind source has committed. See §6.
        if ($batch->source->kind === ImportSourceKind::Lms) {
            $sisCommitted = ImportBatch::query()
                ->where('entity_type', ImportEntityType::Student)
                ->where('status', ImportBatchStatus::Committed)
                ->whereHas('source', fn ($q) => $q->where('kind', ImportSourceKind::Sis))
                ->exists();

            if (! $sisCommitted) {
                throw new RuntimeException(__('import.error_code.E_CANVAS_BEFORE_POPULI'));
            }
        }

        $this->audit->withAudit($actor, 'import.batch_commit', function () use ($batch, $actor) {
            DB::transaction(function () use ($batch) {
                $this->applyRows($batch, persist: true);
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
     * The single code path behind both the dry run and the real commit. Row-level
     * identity resolution walks the full matching ladder from §6 of the plan:
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
    private function applyRows(ImportBatch $batch, bool $persist): array
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

        if ($best !== null && $bestPercent >= 60.0) {
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
     * `status = Pending` filter below is exactly that guard.
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
     * refused per-row when the created user is now referenced by native data (a role
     * assignment, an enrollment, a payment) — the rollback names exactly what blocks it
     * rather than failing vaguely.
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
