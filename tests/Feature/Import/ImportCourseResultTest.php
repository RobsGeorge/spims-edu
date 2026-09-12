<?php

namespace Tests\Feature\Import;

use App\Enums\ImportEntityType;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\ImportGradeMapping;
use App\Models\ImportLink;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Full pipeline for the COURSE_RESULT entity type — docs/legacy-data-import-plan.md §7.
 */
class ImportCourseResultTest extends TestCase
{
    use RefreshDatabase;

    private function populi(): ImportSource
    {
        return ImportSource::query()->create([
            'code' => 'POPULI',
            'name' => 'Populi',
            'kind' => 'SIS',
            'precedence' => 1,
            'gpa_scale_max' => 4.00,
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    /**
     * Simulates a student already imported and linked by an earlier STUDENT batch.
     */
    private function linkedStudent(ImportSource $source, string $legacyId): User
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        ImportLink::query()->create([
            'source_id' => $source->id,
            'entity_type' => 'user',
            'legacy_id' => $legacyId,
            'target_type' => User::class,
            'target_id' => $student->id,
        ]);

        return $student;
    }

    private function gradeMapping(ImportSource $source, string $legacyLetter, string $spimsLetter, float $points, bool $passing = true): ImportGradeMapping
    {
        return ImportGradeMapping::query()->create([
            'source_id' => $source->id,
            'legacy_letter' => $legacyLetter,
            'spims_letter' => $spimsLetter,
            'gpa_points' => $points,
            'is_passing' => $passing,
            'counts_toward_gpa' => false,
        ]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('results.csv', $content);
    }

    private function service(): ImportBatchService
    {
        return app(ImportBatchService::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapping(): array
    {
        return [
            ['column' => 'LegacyID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Course', 'target_field' => 'course_code', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Title', 'target_field' => 'course_title', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Term', 'target_field' => 'term', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Grade', 'target_field' => 'legacy_letter', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Percent', 'target_field' => 'legacy_percent', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Credits', 'target_field' => 'credit_hours', 'transform' => 'trim', 'options' => [], 'ignored' => false],
        ];
    }

    #[Test]
    public function committing_a_course_result_creates_a_shadow_course_and_offering_on_no_match(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->gradeMapping($source, 'A', 'A', 4.0);
        $student = $this->linkedStudent($source, '9001');

        $csv = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9001,BIB101,Intro to Bible,2019FA,A,95,3\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);
        $this->assertSame(0, $batch->warning_count);

        $service->commit($actor, $batch->fresh());

        $course = Course::query()->where('code', 'BIB101')->firstOrFail();
        $this->assertSame('POPULI', $course->source_system);
        $this->assertFalse($course->active);
        $this->assertSame(3, $course->credit_hours);

        $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();
        $this->assertSame('POPULI', $offering->source_system);
        $this->assertSame('2019FA', $offering->legacy_term);
        $this->assertSame(OfferingStatus::Archived, $offering->status);

        $enrollment = Enrollment::query()->where('student_id', $student->id)->where('offering_id', $offering->id)->firstOrFail();
        $this->assertSame('POPULI', $enrollment->source_system);
        $this->assertSame('A', $enrollment->final_letter);

        $record = AcademicRecord::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame('A', $record->letter_grade);
        $this->assertSame(3, $record->credit_hours);
        $this->assertSame('2019FA', $record->term);
        $this->assertFalse($record->counts_toward_gpa, 'Every legacy row imports with counts_toward_gpa forced false.');
        $this->assertSame('POPULI', $record->source_system);
    }

    #[Test]
    public function an_exact_live_course_code_match_is_reused_instead_of_shadowed(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->gradeMapping($source, 'B', 'B', 3.0);
        $student = $this->linkedStudent($source, '9002');

        $liveCourse = Course::query()->create([
            'code' => 'BIB101',
            'title' => 'Intro to Bible',
            'credit_hours' => 3,
            'active' => true,
        ]);

        $csv = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9002,BIB101,Intro to Bible,2019FA,B,85,3\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertSame(1, Course::query()->where('code', 'BIB101')->count(), 'No second, shadow course should be created for an exact code match.');

        $record = AcademicRecord::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame($liveCourse->id, $record->course_id);
    }

    #[Test]
    public function a_credit_hours_mismatch_against_a_live_course_is_a_warning_not_an_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->gradeMapping($source, 'A', 'A', 4.0);
        $this->linkedStudent($source, '9003');

        Course::query()->create([
            'code' => 'BIB101',
            'title' => 'Intro to Bible',
            'credit_hours' => 4,
            'active' => true,
        ]);

        $csv = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9003,BIB101,Intro to Bible,2019FA,A,95,3\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);
        $this->assertSame(1, $batch->warning_count);

        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $codes = collect($row->messages)->pluck('code');
        $this->assertTrue($codes->contains('W_CREDIT_HOURS_DIFFER'));

        $service->commit($actor, $batch->fresh());
        $record = AcademicRecord::query()->firstOrFail();
        $this->assertSame(3, $record->credit_hours, 'The record keeps the legacy credit hours, not the course\'s own.');
    }

    #[Test]
    public function an_unmapped_grade_letter_is_a_hard_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '9004');
        // Deliberately no ImportGradeMapping row for 'Z'.

        $csv = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9004,BIB101,Intro to Bible,2019FA,Z,95,3\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $codes = collect($row->messages)->pluck('code');
        $this->assertTrue($codes->contains('E_UNMAPPED_GRADE'));

        $service->commit($actor, $batch->fresh());
        $this->assertSame(0, AcademicRecord::query()->count(), 'A row blocked by a hard error must not be committed.');
    }

    #[Test]
    public function a_legacy_id_never_imported_as_a_student_is_a_hard_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->gradeMapping($source, 'A', 'A', 4.0);
        // Deliberately no ImportLink for '9999' — no STUDENT batch ever imported them.

        $csv = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9999,BIB101,Intro to Bible,2019FA,A,95,3\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $codes = collect($row->messages)->pluck('code');
        $this->assertTrue($codes->contains('E_UNKNOWN_STUDENT'));
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->gradeMapping($source, 'A', 'A', 4.0);
        $this->linkedStudent($source, '9005');

        $csv = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9005,BIB101,Intro to Bible,2019FA,A,95,3\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $coursesBefore = Course::query()->count();
        $recordsBefore = AcademicRecord::query()->count();

        $report = $service->dryRun($batch->fresh());

        $this->assertSame(1, $report['create']);
        $this->assertSame($coursesBefore, Course::query()->count());
        $this->assertSame($recordsBefore, AcademicRecord::query()->count());
    }

    #[Test]
    public function a_second_import_of_the_same_row_updates_instead_of_duplicating(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->gradeMapping($source, 'A', 'A', 4.0);
        $this->gradeMapping($source, 'B', 'B', 3.0);
        $this->linkedStudent($source, '9006');

        $csv1 = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9006,BIB101,Intro to Bible,2019FA,A,95,3\n");
        $batch1 = $service->createFromUpload($actor, $source, $csv1, null, entityType: ImportEntityType::CourseResult);
        $batch1 = $service->updateMapping($batch1, $this->mapping());
        $batch1 = $service->validate($batch1);
        $service->commit($actor, $batch1->fresh());

        $this->assertSame(1, AcademicRecord::query()->count());

        // A corrected re-export with a different grade for the same (student, course, term).
        $csv2 = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9006,BIB101,Intro to Bible,2019FA,B,85,3\n");
        $batch2 = $service->createFromUpload($actor, $source, $csv2, null, entityType: ImportEntityType::CourseResult);
        $batch2 = $service->updateMapping($batch2, $this->mapping());
        $batch2 = $service->validate($batch2);
        $service->commit($actor, $batch2->fresh());

        $this->assertSame(1, AcademicRecord::query()->count(), 'Re-importing the same (student, course, term) must update, not duplicate.');
        $this->assertSame('B', AcademicRecord::query()->firstOrFail()->letter_grade);
    }

    #[Test]
    public function committing_a_course_result_batch_writes_one_audit_log_entry(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->gradeMapping($source, 'A', 'A', 4.0);
        $this->linkedStudent($source, '9007');

        $csv = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9007,BIB101,Intro to Bible,2019FA,A,95,3\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'import.batch_commit',
        ]);
    }

    #[Test]
    public function committing_a_course_result_batch_sends_no_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->gradeMapping($source, 'A', 'A', 4.0);
        $this->linkedStudent($source, '9008');

        $csv = $this->csv("LegacyID,Course,Title,Term,Grade,Percent,Credits\n9008,BIB101,Intro to Bible,2019FA,A,95,3\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }
}
