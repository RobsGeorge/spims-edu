<?php

namespace App\Services\Import;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportEntityType;
use App\Enums\ImportPopulation;
use App\Enums\ImportRowAction;
use App\Enums\ImportRowStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\StudentProgramStatus;
use App\Enums\UserStatus;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\ImportBatch;
use App\Models\ImportGradeMapping;
use App\Models\ImportLink;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates the whole batch pipeline for two entity types:
 * upload -> profile -> map -> validate -> dry run -> commit -> (rollback).
 * See docs/legacy-data-import-plan.md §10.
 *
 * STUDENT (L0/L1): identity + program linkage — rungs 1 and 3 of the matching ladder
 * (import_links and exact email). A `program_code` mapping links only to a program
 * that already exists in the live catalog (the "exact code match" half of D2) and
 * never creates a shadow program.
 *
 * COURSE_RESULT (L3): course results against the shadow catalog — §7. Every method
 * below dispatches on `$batch->entity_type` at the top and keeps the two row-level
 * pipelines in separate private methods; the STUDENT branch is untouched from L1.
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
        ?ImportPopulation $population,
        ImportEntityType $entityType = ImportEntityType::Student,
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
     * Re-reads the staged file, applies the mapping, and writes one ImportRow per data
     * row with validation messages. Nothing outside `import_rows` is written here.
     * Dispatches on entity type; see the class docblock.
     */
    public function validate(ImportBatch $batch): ImportBatch
    {
        return match ($batch->entity_type) {
            ImportEntityType::CourseResult => $this->validateCourseResult($batch),
            default => $this->validateStudent($batch),
        };
    }

    private function validateStudent(ImportBatch $batch): ImportBatch
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

        $this->audit->withAudit($actor, 'import.batch_commit', function () use ($batch, $actor) {
            DB::transaction(function () use ($batch) {
                $this->applyRows($batch, persist: true);
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
     * The single code path behind both the dry run and the real commit. Dispatches on
     * entity type; see the class docblock.
     *
     * @return array<string, int>
     */
    private function applyRows(ImportBatch $batch, bool $persist): array
    {
        return match ($batch->entity_type) {
            ImportEntityType::CourseResult => $this->applyCourseResultRows($batch, $persist),
            default => $this->applyStudentRows($batch, $persist),
        };
    }

    /**
     * Row-level identity resolution for STUDENT: rung 1 (an existing import_links row)
     * then rung 3 (exact normalised email match). Anything else is created new. See §6
     * of the plan for the full ladder — rungs 2, 4-6 (Canvas crosswalk, student number,
     * DOB triple, and the human merge queue) are not yet implemented.
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

        $isCourseResult = $batch->entity_type === ImportEntityType::CourseResult;

        $this->audit->withAudit($actor, 'import.batch_rollback', function () use ($batch, $actor, $isCourseResult, &$blocked, &$rolledBack) {
            DB::transaction(function () use ($batch, $actor, $isCourseResult, &$blocked, &$rolledBack) {
                $rows = ImportRow::query()->where('batch_id', $batch->id)
                    ->where('status', ImportRowStatus::Applied)
                    ->where('action', ImportRowAction::Create)
                    ->get();

                foreach ($rows as $row) {
                    $reason = $isCourseResult
                        ? $this->rollbackCourseResultRow($batch, $row)
                        : $this->rollbackStudentRow($batch, $row);

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
}
