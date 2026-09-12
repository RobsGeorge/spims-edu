<?php

namespace Tests\Feature\Import;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\ImportEntityType;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\ImportGradeMapping;
use App\Models\ImportLink;
use App\Models\ImportSource;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Gradebook\GradebookService;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The single most important acceptance criterion for L3: importing a legacy course
 * result for a student who already has a live, computed GPA must not move it — and a
 * registrar's deliberate promotion must, in an isolated and audited way.
 * See docs/legacy-data-import-plan.md §7, §15.
 */
class ImportGpaIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    /**
     * A student with one native, locked, passing enrollment attached to an active
     * program — the standard shape that produces a non-null cached_gpa.
     *
     * @return array{student: User, program: Program, sp: StudentProgram}
     */
    private function studentWithComputedGpa(): array
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();

        $program = Program::query()->create([
            'code' => 'DIP-NATIVE',
            'name' => 'Native Diploma',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'active' => true,
        ]);

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $course = Course::query()->create([
            'code' => 'NAT101',
            'title' => 'Native Course',
            'credit_hours' => 3,
            'active' => true,
        ]);

        ProgramCourse::query()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'requirement' => RequirementType::Required,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'student_program_id' => $sp->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'final_percent' => 90,
            'final_letter' => 'A',
            'final_gpa_points' => 4,
            'grade_status' => GradeStatus::Submitted,
            'grade_type' => GradeType::Standard,
        ]);

        app(GradebookService::class)->lockGrades($instructor, $offering);

        return ['student' => $student, 'program' => $program, 'sp' => $sp];
    }

    #[Test]
    public function importing_a_legacy_course_result_never_moves_the_students_cached_gpa(): void
    {
        ['student' => $student, 'sp' => $sp] = $this->studentWithComputedGpa();

        $gpaBefore = $sp->fresh()->cached_gpa;
        $this->assertNotNull($gpaBefore, 'The fixture must produce a computed GPA before the import.');

        $source = ImportSource::query()->create([
            'code' => 'POPULI',
            'name' => 'Populi',
            'kind' => 'SIS',
            'precedence' => 1,
            'gpa_scale_max' => 4.00,
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'active' => true,
        ]);

        ImportLink::query()->create([
            'source_id' => $source->id,
            'entity_type' => 'user',
            'legacy_id' => 'LEG1',
            'target_type' => User::class,
            'target_id' => $student->id,
        ]);

        ImportGradeMapping::query()->create([
            'source_id' => $source->id,
            'legacy_letter' => 'A',
            'spims_letter' => 'A',
            'gpa_points' => 4.0,
            'is_passing' => true,
            'counts_toward_gpa' => false,
        ]);

        $actor = $this->admin();
        $service = app(ImportBatchService::class);
        $csv = UploadedFile::fake()->createWithContent(
            'legacy.csv',
            "LegacyID,Course,Term,Grade,Percent,Credits\nLEG1,LEGACY101,2015FA,A,98,4\n",
        );

        $mapping = [
            ['column' => 'LegacyID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Course', 'target_field' => 'course_code', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Term', 'target_field' => 'term', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Grade', 'target_field' => 'legacy_letter', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Percent', 'target_field' => 'legacy_percent', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Credits', 'target_field' => 'credit_hours', 'transform' => 'trim', 'options' => [], 'ignored' => false],
        ];

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);
        $this->assertSame(0, $batch->error_count);
        $service->commit($actor, $batch->fresh());

        $legacyRecord = AcademicRecord::query()->where('term', '2015FA')->firstOrFail();
        $this->assertFalse($legacyRecord->counts_toward_gpa);

        // The whole point of the filter: a byte-identical GPA before and after.
        $this->assertSame($gpaBefore, $sp->fresh()->cached_gpa);
    }

    #[Test]
    public function promoting_a_legacy_record_moves_the_gpa_in_an_isolated_audited_way(): void
    {
        ['student' => $student, 'program' => $program, 'sp' => $sp] = $this->studentWithComputedGpa();
        $gpaBefore = $sp->fresh()->cached_gpa;

        $source = ImportSource::query()->create([
            'code' => 'POPULI',
            'name' => 'Populi',
            'kind' => 'SIS',
            'precedence' => 1,
            'gpa_scale_max' => 4.00,
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'active' => true,
        ]);

        ImportLink::query()->create([
            'source_id' => $source->id,
            'entity_type' => 'user',
            'legacy_id' => 'LEG2',
            'target_type' => User::class,
            'target_id' => $student->id,
        ]);

        ImportGradeMapping::query()->create([
            'source_id' => $source->id,
            'legacy_letter' => 'C',
            'spims_letter' => 'C',
            'gpa_points' => 2.0,
            'is_passing' => true,
            'counts_toward_gpa' => false,
        ]);

        // The legacy row targets NAT101 — the very course the program already
        // requires (see studentWithComputedGpa()) — via an exact live-course-code
        // match, so promoting it will actually move the fulfilment's GPA contribution.
        $actor = $this->admin();
        $service = app(ImportBatchService::class);
        $csv = UploadedFile::fake()->createWithContent(
            'legacy2.csv',
            "LegacyID,Course,Term,Grade,Percent,Credits,Program\nLEG2,NAT101,2015SP,C,72,3,DIP-NATIVE\n",
        );

        $mapping = [
            ['column' => 'LegacyID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Course', 'target_field' => 'course_code', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Term', 'target_field' => 'term', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Grade', 'target_field' => 'legacy_letter', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Percent', 'target_field' => 'legacy_percent', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Credits', 'target_field' => 'credit_hours', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Program', 'target_field' => 'program_code', 'transform' => 'trim', 'options' => [], 'ignored' => false],
        ];

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $legacyRecord = AcademicRecord::query()->where('term', '2015SP')->firstOrFail();
        $this->assertFalse($legacyRecord->counts_toward_gpa);
        $this->assertSame($gpaBefore, $sp->fresh()->cached_gpa, 'Attaching a fulfilment must not itself move the GPA.');
        $this->assertSame(
            1,
            ProgramRequirementFulfillment::query()->where('academic_record_id', $legacyRecord->id)->count(),
            'program_code should attach a fulfilment for a course the program requires.'
        );

        app(GradebookService::class)->promoteToTransferCredit($actor, $legacyRecord);

        $this->assertTrue($legacyRecord->fresh()->counts_toward_gpa);
        $this->assertNotSame($gpaBefore, $sp->fresh()->cached_gpa, 'Promoting the record must recompute the GPA.');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'import.promote_transfer_credit',
        ]);
    }
}
