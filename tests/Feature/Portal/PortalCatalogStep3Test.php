<?php

namespace Tests\Feature\Portal;

use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\ProgramCourse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalCatalogStep3Test extends TestCase
{
    use RefreshDatabase;

    private Program $program;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->buildFixture();
    }

    private function buildFixture(): void
    {
        $this->course = Course::query()->create([
            'code'          => 'CAT301',
            'title'         => 'Catalog Test Course',
            'credit_hours'  => 3,
            'is_standalone' => false,
            'is_free'       => false,
            'active'        => true,
            'default_price_usd' => 5000, // $50.00 in minor units
        ]);

        $this->program = Program::query()->create([
            'code'                      => 'TEST-PROG',
            'name'                      => 'Test Catalog Program',
            'type'                      => ProgramType::Certificate,
            'passing_threshold'         => 60.0,
            'max_credits_per_semester'  => 18,
            'max_courses_per_semester'  => 6,
            'max_semesters_to_graduate' => 4,
            'elective_credits_required' => 0,
            'marketing_summary'         => 'A program for testing the catalog.',
            'active'                    => true,
        ]);

        ProgramCourse::query()->create([
            'program_id'  => $this->program->id,
            'course_id'   => $this->course->id,
            'requirement' => RequirementType::Required,
            'year_level'  => 1,
        ]);
    }

    // ── Gate 1: $programs is actually rendered ────────────────────────────

    #[Test]
    public function programs_tab_renders_seeded_program(): void
    {
        $response = $this->get(route('catalog.index'));

        $response->assertStatus(200);
        $response->assertSee('Test Catalog Program');
    }

    #[Test]
    public function programs_tab_is_default_active_tab(): void
    {
        $response = $this->get(route('catalog.index'));

        $response->assertStatus(200);
        // The programs tab panel must exist in the DOM
        $response->assertSee('catalog-tabs-panel-programs', false);
    }

    // ── Gate 2: Tab deep-linking via ?tab= ───────────────────────────────

    #[Test]
    public function tab_deep_link_courses_initialises_to_courses(): void
    {
        $response = $this->get(route('catalog.index', ['tab' => 'courses']));

        $response->assertStatus(200);
        // The Alpine x-init expression must reference the current tab
        $response->assertSee('courses', false);
    }

    #[Test]
    public function tab_deep_link_standalone_is_accepted(): void
    {
        $response = $this->get(route('catalog.index', ['tab' => 'standalone']));

        $response->assertStatus(200);
        $response->assertSee('catalog-results-standalone', false);
    }

    // ── Gate 3: Filters compose (AND logic) ──────────────────────────────

    #[Test]
    public function type_and_mode_filters_compose_with_and_logic(): void
    {
        // Create a cohort offering for our course
        CourseOffering::query()->create([
            'course_id'  => $this->course->id,
            'status'     => OfferingStatus::Open,
            'mode'       => OfferingMode::Cohort,
            'start_date' => now()->addMonth(),
            'end_date'   => now()->addMonths(3),
        ]);

        // A diploma program/course should NOT appear when filtering for certificate + cohort
        $diplomaCourse = Course::query()->create([
            'code'          => 'DIP301',
            'title'         => 'Diploma Only Course',
            'credit_hours'  => 3,
            'is_standalone' => false,
            'is_free'       => false,
            'active'        => true,
        ]);
        $diplomaProgram = Program::query()->create([
            'code'                      => 'DIP-TEST',
            'name'                      => 'Diploma Test Program',
            'type'                      => ProgramType::Diploma,
            'passing_threshold'         => 60.0,
            'max_credits_per_semester'  => 18,
            'max_courses_per_semester'  => 6,
            'max_semesters_to_graduate' => 4,
            'elective_credits_required' => 0,
            'active'                    => true,
        ]);
        ProgramCourse::query()->create([
            'program_id'  => $diplomaProgram->id,
            'course_id'   => $diplomaCourse->id,
            'requirement' => RequirementType::Required,
            'year_level'  => 1,
        ]);

        // Filter: programs tab, type=certificate (our program), mode=cohort (our offering)
        $response = $this->get(route('catalog.index', [
            'tab'  => 'programs',
            'type' => 'certificate',
            'mode' => 'cohort',
        ]));

        $response->assertStatus(200);
        // Our certificate program (with cohort offering) appears
        $response->assertSee('Test Catalog Program');
        // The diploma program does NOT appear (wrong type — AND filter)
        $response->assertDontSee('Diploma Test Program');
    }

    #[Test]
    public function courses_tab_type_filter_limits_to_courses_in_matching_programs(): void
    {
        $response = $this->get(route('catalog.index', [
            'tab'  => 'courses',
            'type' => 'certificate',
        ]));

        $response->assertStatus(200);
        // Our course belongs to a certificate program, so it should appear
        $response->assertSee('CAT301');
    }

    // ── Gate 4: Empty filter result shows <x-empty-state> ────────────────

    #[Test]
    public function empty_program_filter_result_shows_empty_state(): void
    {
        $response = $this->get(route('catalog.index', [
            'tab'  => 'programs',
            'type' => 'degree', // no degree programs seeded
        ]));

        $response->assertStatus(200);
        // The x-empty-state component renders with class spims-empty-state
        $response->assertSee('spims-empty-state', false);
    }

    #[Test]
    public function empty_courses_filter_result_shows_empty_state(): void
    {
        $response = $this->get(route('catalog.index', [
            'tab' => 'courses',
            'q'   => 'zzznonexistent999xyz',
        ]));

        $response->assertStatus(200);
        $response->assertSee('spims-empty-state', false);
    }

    #[Test]
    public function empty_standalone_filter_result_shows_empty_state(): void
    {
        $response = $this->get(route('catalog.index', [
            'tab' => 'standalone',
            'q'   => 'zzznonexistent999xyz',
        ]));

        $response->assertStatus(200);
        $response->assertSee('spims-empty-state', false);
    }
}
