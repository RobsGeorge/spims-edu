<?php

namespace Tests\Feature\Import;

use App\Enums\ImportEntityType;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\ImportGradeMapping;
use App\Models\ImportLink;
use App\Models\ImportSource;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Gradebook\GradebookService;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * D7 — registrar-approved transfer credit, one record at a time. See
 * docs/legacy-data-import-plan.md §7, GradebookService::promoteToTransferCredit().
 */
class ImportTransferCreditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    /**
     * @return array{record: AcademicRecord, other_record: AcademicRecord, sp: StudentProgram}
     */
    private function importTwoLegacyRecords(): array
    {
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

        $student = User::factory()->withRole(RoleType::Student)->create();
        ImportLink::query()->create([
            'source_id' => $source->id,
            'entity_type' => 'user',
            'legacy_id' => 'TC1',
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

        $program = Program::query()->create([
            'code' => 'DIP-TC',
            'name' => 'Transfer Credit Diploma',
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

        $actor = $this->admin();
        $service = app(ImportBatchService::class);
        $csv = UploadedFile::fake()->createWithContent(
            'tc.csv',
            "LegacyID,Course,Term,Grade,Percent,Credits,Program\n"
            ."TC1,TC101,2012FA,A,95,3,DIP-TC\n"
            ."TC1,TC102,2012FA,A,90,3,DIP-TC\n",
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

        // Both courses are on the program's requirement list.
        foreach (['TC101', 'TC102'] as $code) {
            $course = Course::query()->where('code', $code)->first() ?? Course::query()->create([
                'code' => $code, 'title' => $code, 'credit_hours' => 3, 'active' => true,
            ]);
            ProgramCourse::query()->create([
                'program_id' => $program->id,
                'course_id' => $course->id,
                'requirement' => RequirementType::Elective,
            ]);
        }

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);
        $this->assertSame(0, $batch->error_count);
        $service->commit($actor, $batch->fresh());

        $record = AcademicRecord::query()->whereHas('course', fn ($q) => $q->where('code', 'TC101'))->firstOrFail();
        $otherRecord = AcademicRecord::query()->whereHas('course', fn ($q) => $q->where('code', 'TC102'))->firstOrFail();

        return ['record' => $record, 'other_record' => $otherRecord, 'sp' => $sp];
    }

    #[Test]
    public function promoting_one_record_only_affects_that_record(): void
    {
        ['record' => $record, 'other_record' => $other] = $this->importTwoLegacyRecords();
        $actor = $this->admin();

        $this->assertFalse($record->counts_toward_gpa);
        $this->assertFalse($other->counts_toward_gpa);

        app(GradebookService::class)->promoteToTransferCredit($actor, $record);

        $this->assertTrue($record->fresh()->counts_toward_gpa);
        $this->assertFalse($other->fresh()->counts_toward_gpa, 'Promotion is one record at a time — the sibling row must be untouched.');
    }

    #[Test]
    public function promoting_a_record_is_audited(): void
    {
        ['record' => $record] = $this->importTwoLegacyRecords();
        $actor = $this->admin();

        app(GradebookService::class)->promoteToTransferCredit($actor, $record);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'import.promote_transfer_credit',
            'entity_type' => 'AcademicRecord',
            'entity_id' => $record->id,
        ]);
    }

    #[Test]
    public function promoting_twice_is_idempotent_and_does_not_double_audit_incorrectly(): void
    {
        ['record' => $record] = $this->importTwoLegacyRecords();
        $actor = $this->admin();

        app(GradebookService::class)->promoteToTransferCredit($actor, $record);
        app(GradebookService::class)->promoteToTransferCredit($actor, $record->fresh());

        $this->assertTrue($record->fresh()->counts_toward_gpa);
        $this->assertSame(
            2,
            \App\Models\AuditLog::query()->where('action', 'import.promote_transfer_credit')->count(),
            'Each call is its own audited action, even when the record was already promoted.'
        );
    }

    #[Test]
    public function a_role_without_import_commit_cannot_promote(): void
    {
        ['record' => $record] = $this->importTwoLegacyRecords();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();

        $this->expectException(\App\Exceptions\AuthorizationException::class);
        app(GradebookService::class)->promoteToTransferCredit($instructor, $record);
    }

    #[Test]
    public function the_transcript_promote_route_requires_import_commit_permission(): void
    {
        ['record' => $record] = $this->importTwoLegacyRecords();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->post(route('transcript.promote', $record))
            ->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('transcript.promote', $record))
            ->assertRedirect();

        $this->assertTrue($record->fresh()->counts_toward_gpa);
    }
}
