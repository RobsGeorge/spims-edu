<?php

namespace Tests\Feature\Import;

use App\Enums\ImportEntityType;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\ImportGradeMapping;
use App\Models\ImportLink;
use App\Models\ImportSource;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A shadow course/offering created by the legacy importer must appear in none of: the
 * public catalog, admin.offerings.index, the enrollment roster picker, the live
 * gradebook grid, or the reports module. See docs/legacy-data-import-plan.md §4.2, D2.
 */
class ImportCatalogIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    /**
     * Commits one COURSE_RESULT row that creates a shadow course, offering, enrollment
     * and academic record, and returns them.
     *
     * @return array{course: Course, offering: CourseOffering}
     */
    private function importOneShadowResult(): array
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
            'legacy_id' => 'SHDW1',
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
            'shadow.csv',
            "LegacyID,Course,Title,Term,Grade,Percent,Credits\nSHDW1,SHDW101,Shadow Course,2010FA,A,95,3\n",
        );
        $mapping = [
            ['column' => 'LegacyID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Course', 'target_field' => 'course_code', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Title', 'target_field' => 'course_title', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Term', 'target_field' => 'term', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Grade', 'target_field' => 'legacy_letter', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Percent', 'target_field' => 'legacy_percent', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Credits', 'target_field' => 'credit_hours', 'transform' => 'trim', 'options' => [], 'ignored' => false],
        ];

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::CourseResult);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $course = Course::query()->where('code', 'SHDW101')->firstOrFail();
        $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();

        return ['course' => $course, 'offering' => $offering];
    }

    #[Test]
    public function a_shadow_course_never_appears_in_the_public_catalog(): void
    {
        ['course' => $course] = $this->importOneShadowResult();
        $this->assertFalse($course->active);

        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->get(route('catalog.index', ['tab' => 'courses']))
            ->assertOk()
            ->assertDontSee('SHDW101');
    }

    #[Test]
    public function a_shadow_offering_never_appears_in_admin_offerings_index(): void
    {
        $this->importOneShadowResult();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.offerings.index'))
            ->assertOk()
            ->assertDontSee('SHDW101');
    }

    #[Test]
    public function a_shadow_offering_never_appears_in_the_enrollment_roster_picker(): void
    {
        $this->importOneShadowResult();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.enrollments.index'))
            ->assertOk()
            ->assertDontSee('SHDW101');
    }

    #[Test]
    public function a_shadow_offering_is_refused_by_the_live_gradebook_grid(): void
    {
        ['offering' => $offering] = $this->importOneShadowResult();
        // gradebook.configure is not granted to ADMINISTRATIVE_ADMIN; use the role
        // that actually holds it unscoped.
        $academicAdmin = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->actingAs($academicAdmin)
            ->get(route('admin.gradebook.show', $offering))
            ->assertNotFound();
    }

    #[Test]
    public function a_shadow_enrollment_never_appears_in_the_headcount_or_grade_reports(): void
    {
        $this->importOneShadowResult();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.reports.headcount'))
            ->assertOk()
            ->assertDontSee('SHDW101');

        $this->actingAs($admin)
            ->get(route('admin.reports.grades'))
            ->assertOk()
            ->assertDontSee('SHDW101');
    }
}
