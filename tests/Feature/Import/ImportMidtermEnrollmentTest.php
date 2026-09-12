<?php

namespace Tests\Feature\Import;

use App\Enums\ComponentKind;
use App\Enums\GradeStatus;
use App\Enums\ImportEntityType;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\GradebookComponentScore;
use App\Models\ImportLink;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\Semester;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L7 — mid-term cutover (MIDTERM_ENROLLMENT). See docs/legacy-data-import-plan.md §22.
 */
class ImportMidtermEnrollmentTest extends TestCase
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

    private function semester(string $name = 'Fall 2026'): Semester
    {
        $year = AcademicYear::query()->create([
            'name' => '2026/2027',
            'start_date' => now()->subMonths(1),
            'end_date' => now()->addMonths(8),
        ]);

        return Semester::query()->create([
            'academic_year_id' => $year->id,
            'name' => $name,
            'start_date' => now()->subWeeks(1),
            'end_date' => now()->addMonths(3),
            'registration_start' => now()->subDays(10),
            'registration_end' => now()->addDays(10),
            'add_drop_end_week' => 4,
            'last_withdrawal_week' => 10,
            'withdrawal_refund_percent' => 50,
        ]);
    }

    /**
     * A live course + live offering (never source_system) + one gradebook component,
     * so a MIDTERM_ENROLLMENT row has a real target to resolve — exactly the
     * registrar-built infrastructure §22.2 requires.
     */
    private function liveOffering(Semester $semester, string $courseCode = 'BIB201', string $componentName = 'Midterm Exam'): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $courseCode,
            'title' => 'Live course',
            'credit_hours' => 3,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        GradebookComponent::query()->create([
            'offering_id' => $offering->id,
            'name' => $componentName,
            'weight_percent' => 30,
            'kind' => ComponentKind::Exam,
        ]);

        return $offering;
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('midterm.csv', $content);
    }

    private function service(): ImportBatchService
    {
        return app(ImportBatchService::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapping(bool $withOfferingId = false): array
    {
        $mapping = [
            ['column' => 'LegacyID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Course', 'target_field' => 'course_code', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Semester', 'target_field' => 'semester_name', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Component', 'target_field' => 'component_name', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Score', 'target_field' => 'score', 'transform' => 'trim', 'options' => [], 'ignored' => false],
        ];

        if ($withOfferingId) {
            $mapping[] = ['column' => 'OfferingId', 'target_field' => 'offering_id', 'transform' => 'trim', 'options' => [], 'ignored' => false];
        }

        return $mapping;
    }

    #[Test]
    public function committing_a_midterm_enrollment_row_creates_a_live_enrollment_and_score(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $student = $this->linkedStudent($source, '5001');
        $semester = $this->semester();
        $offering = $this->liveOffering($semester);

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5001,BIB201,Fall 2026,Midterm Exam,82.5\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);
        $this->assertSame(0, $batch->warning_count);

        $service->commit($actor, $batch->fresh());

        $enrollment = Enrollment::query()->where('student_id', $student->id)->where('offering_id', $offering->id)->firstOrFail();
        $this->assertSame('POPULI', $enrollment->source_system);
        $this->assertSame(GradeStatus::InProgress, $enrollment->grade_status);

        $component = GradebookComponent::query()->where('offering_id', $offering->id)->firstOrFail();
        $score = GradebookComponentScore::query()->where('component_id', $component->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertEqualsWithDelta(82.5, $score->score, 0.0001);
        $this->assertSame($actor->id, $score->updated_by_id);

        // Never a shadow course/offering — the whole point of L7 vs COURSE_RESULT.
        $this->assertSame(1, Course::query()->count());
        $this->assertSame(1, CourseOffering::query()->count());
    }

    #[Test]
    public function an_unresolvable_offering_is_a_hard_error_and_never_shadow_creates(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '5002');
        $semester = $this->semester();
        $this->liveOffering($semester, 'BIB201');
        // Deliberately no offering for 'NOPE'.

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5002,NOPE,Fall 2026,Midterm Exam,80\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertTrue(collect($row->messages)->pluck('code')->contains('E_OFFERING_NOT_FOUND'));

        $coursesBefore = Course::query()->count();
        $offeringsBefore = CourseOffering::query()->count();

        $service->commit($actor, $batch->fresh());

        $this->assertSame($coursesBefore, Course::query()->count(), 'A blocked row must never shadow-create a course.');
        $this->assertSame($offeringsBefore, CourseOffering::query()->count(), 'A blocked row must never shadow-create an offering.');
        $this->assertSame(0, Enrollment::query()->count());
    }

    #[Test]
    public function an_ambiguous_offering_is_a_hard_error_unless_disambiguated_by_offering_id(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '5003');
        $semester = $this->semester();

        // Two live offerings of the exact same course in the exact same semester.
        $course = Course::query()->create(['code' => 'BIB201', 'title' => 'Live course', 'credit_hours' => 3, 'active' => true]);
        $offeringA = CourseOffering::query()->create(['course_id' => $course->id, 'semester_id' => $semester->id, 'mode' => OfferingMode::Cohort, 'status' => 'OPEN']);
        $offeringB = CourseOffering::query()->create(['course_id' => $course->id, 'semester_id' => $semester->id, 'mode' => OfferingMode::Cohort, 'status' => 'OPEN']);
        GradebookComponent::query()->create(['offering_id' => $offeringA->id, 'name' => 'Midterm Exam', 'weight_percent' => 30, 'kind' => ComponentKind::Exam]);
        GradebookComponent::query()->create(['offering_id' => $offeringB->id, 'name' => 'Midterm Exam', 'weight_percent' => 30, 'kind' => ComponentKind::Exam]);

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5003,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertTrue(collect($row->messages)->pluck('code')->contains('E_OFFERING_AMBIGUOUS'));

        // The offering_id escape hatch resolves it unambiguously.
        $csv2 = $this->csv("LegacyID,Course,Semester,Component,Score,OfferingId\n5003,BIB201,Fall 2026,Midterm Exam,80,{$offeringB->id}\n");
        $batch2 = $service->createFromUpload($actor, $source, $csv2, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch2 = $service->updateMapping($batch2, $this->mapping(withOfferingId: true));
        $batch2 = $service->validate($batch2);

        $this->assertSame(0, $batch2->error_count);
        $service->commit($actor, $batch2->fresh());

        $this->assertTrue(Enrollment::query()->where('student_id', ImportLink::query()->where('legacy_id', '5003')->first()->target_id)->where('offering_id', $offeringB->id)->exists());
        $this->assertSame(0, Enrollment::query()->where('offering_id', $offeringA->id)->count());
    }

    #[Test]
    public function an_unmapped_component_name_is_a_hard_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '5004');
        $semester = $this->semester();
        $this->liveOffering($semester, 'BIB201', 'Midterm Exam');
        // The file references a component that does not exist on this offering.

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5004,BIB201,Fall 2026,Final Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertTrue(collect($row->messages)->pluck('code')->contains('E_UNKNOWN_COMPONENT'));

        $service->commit($actor, $batch->fresh());
        $this->assertSame(0, GradebookComponentScore::query()->count());
    }

    #[Test]
    public function a_legacy_id_never_imported_as_a_student_is_a_hard_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $semester = $this->semester();
        $this->liveOffering($semester, 'BIB201');
        // Deliberately no ImportLink for '9999'.

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n9999,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertTrue(collect($row->messages)->pluck('code')->contains('E_UNKNOWN_STUDENT'));
    }

    #[Test]
    public function an_existing_enrollment_on_the_offering_is_refused_never_overwritten(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $student = $this->linkedStudent($source, '5005');
        $semester = $this->semester();
        $offering = $this->liveOffering($semester, 'BIB201');

        // The student already has a native SPIMS enrollment on this offering —
        // simulating an instructor already grading them before the import runs.
        Enrollment::query()->create(['student_id' => $student->id, 'offering_id' => $offering->id]);

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5005,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertTrue(collect($row->messages)->pluck('code')->contains('E_ENROLLMENT_ALREADY_EXISTS'));

        $service->commit($actor, $batch->fresh());
        $this->assertSame(0, GradebookComponentScore::query()->count(), 'Never write a score onto a pre-existing enrollment.');
        $this->assertSame(1, Enrollment::query()->count(), 'The pre-existing enrollment must be untouched, not duplicated.');
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '5006');
        $semester = $this->semester();
        $this->liveOffering($semester, 'BIB201');

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5006,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $enrollmentsBefore = Enrollment::query()->count();
        $scoresBefore = GradebookComponentScore::query()->count();

        $report = $service->dryRun($batch->fresh());

        $this->assertSame(1, $report['enrollments_created']);
        $this->assertSame(1, $report['create']);
        $this->assertSame($enrollmentsBefore, Enrollment::query()->count());
        $this->assertSame($scoresBefore, GradebookComponentScore::query()->count());
    }

    #[Test]
    public function rollback_deletes_the_score_and_the_now_empty_enrollment(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $student = $this->linkedStudent($source, '5007');
        $semester = $this->semester();
        $offering = $this->liveOffering($semester, 'BIB201');

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5007,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertSame(1, Enrollment::query()->count());
        $this->assertSame(1, GradebookComponentScore::query()->count());

        $result = $service->rollback($actor, $batch->fresh());

        $this->assertSame(1, $result['rolled_back']);
        $this->assertSame([], $result['blocked']);
        $this->assertSame(0, GradebookComponentScore::query()->count());
        $this->assertSame(0, Enrollment::query()->where('student_id', $student->id)->where('offering_id', $offering->id)->count());
    }

    #[Test]
    public function rollback_refuses_once_an_instructor_has_edited_the_score_since_import(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $service = $this->service();
        $this->linkedStudent($source, '5008');
        $semester = $this->semester();
        $this->liveOffering($semester, 'BIB201');

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5008,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        // The instructor grades in the live gradebook after go-live.
        $score = GradebookComponentScore::query()->firstOrFail();
        $score->update(['score' => 91, 'updated_by_id' => $instructor->id]);

        $result = $service->rollback($actor, $batch->fresh());

        $this->assertSame(0, $result['rolled_back']);
        $this->assertCount(1, $result['blocked']);
        $this->assertSame(1, GradebookComponentScore::query()->count(), 'A score edited since import must never be silently discarded.');
        $this->assertEqualsWithDelta(91.0, GradebookComponentScore::query()->firstOrFail()->score, 0.0001);
    }

    #[Test]
    public function rollback_refuses_once_grading_has_moved_past_in_progress(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '5009');
        $semester = $this->semester();
        $this->liveOffering($semester, 'BIB201');

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5009,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        Enrollment::query()->update(['grade_status' => 'LOCKED']);

        $result = $service->rollback($actor, $batch->fresh());

        $this->assertSame(0, $result['rolled_back']);
        $this->assertCount(1, $result['blocked']);
        $this->assertSame(1, Enrollment::query()->count());
    }

    #[Test]
    public function committing_a_midterm_enrollment_batch_writes_one_audit_log_entry(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '5010');
        $semester = $this->semester();
        $this->liveOffering($semester, 'BIB201');

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5010,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'import.batch_commit',
        ]);
    }

    #[Test]
    public function committing_a_midterm_enrollment_batch_sends_no_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '5011');
        $semester = $this->semester();
        $this->liveOffering($semester, 'BIB201');

        $csv = $this->csv("LegacyID,Course,Semester,Component,Score\n5011,BIB201,Fall 2026,Midterm Exam,80\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::MidtermEnrollment);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }
}
