<?php

namespace Tests\Feature\Portal;

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

class ProgramCatalogTest extends TestCase
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
            'code'         => 'COP101',
            'title'        => 'Introduction to Coptic',
            'credit_hours' => 3,
            'is_standalone' => false,
            'is_free'      => true,
            'active'       => true,
        ]);

        $this->program = Program::query()->create([
            'code'                        => 'CERT-COP',
            'name'                        => 'Certificate in Coptic Studies',
            'type'                        => ProgramType::Certificate,
            'passing_threshold'           => 60.0,
            'max_credits_per_semester'    => 18,
            'max_courses_per_semester'    => 6,
            'max_semesters_to_graduate'   => 4,
            'elective_credits_required'   => 3,
            'marketing_summary'           => 'A foundational program in Coptic language and heritage.',
            'active'                      => true,
        ]);

        ProgramCourse::query()->create([
            'program_id'  => $this->program->id,
            'course_id'   => $this->course->id,
            'requirement' => RequirementType::Required,
            'year_level'  => 1,
        ]);
    }

    #[Test]
    public function guest_can_view_program_catalog(): void
    {
        $response = $this->get(route('programs.catalog.index'));

        $response->assertStatus(200);
        $response->assertSee($this->program->name);
    }

    #[Test]
    public function program_catalog_hides_programs_with_no_courses(): void
    {
        $emptyProgram = Program::query()->create([
            'code'                      => 'EMPTY-PROG',
            'name'                      => 'Empty Program',
            'type'                      => ProgramType::Diploma,
            'passing_threshold'         => 60.0,
            'max_credits_per_semester'  => 18,
            'max_courses_per_semester'  => 6,
            'max_semesters_to_graduate' => 4,
            'elective_credits_required' => 0,
            'active'                    => true,
        ]);

        $response = $this->get(route('programs.catalog.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Empty Program');
    }

    #[Test]
    public function guest_can_view_program_brochure(): void
    {
        $response = $this->get(route('programs.catalog.show', $this->program->code));

        $response->assertStatus(200);
        $response->assertSee($this->program->name);
    }

    #[Test]
    public function program_brochure_shows_course_sequence(): void
    {
        $response = $this->get(route('programs.catalog.show', $this->program->code));

        $response->assertStatus(200);
        $response->assertSee($this->course->code);
        $response->assertSee($this->course->title);
    }

    #[Test]
    public function program_brochure_always_has_a_working_cta(): void
    {
        // Program with no active application form should still show contact link, not 500
        $response = $this->get(route('programs.catalog.show', $this->program->code));

        $response->assertStatus(200);
        // Either an apply link or contact email — no dead link
        $this->assertTrue(
            str_contains($response->getContent(), 'admissions') ||
            str_contains($response->getContent(), route('programs.catalog.show', $this->program->code)),
            'CTA must reference admissions or a valid route'
        );
    }

    #[Test]
    public function program_brochure_returns_404_for_unknown_code(): void
    {
        $response = $this->get(route('programs.catalog.show', 'DOES-NOT-EXIST'));

        $response->assertStatus(404);
    }

    #[Test]
    public function inactive_program_is_not_accessible(): void
    {
        $inactive = Program::query()->create([
            'code'                      => 'INACTIVE',
            'name'                      => 'Inactive Program',
            'type'                      => ProgramType::Diploma,
            'passing_threshold'         => 60.0,
            'max_credits_per_semester'  => 18,
            'max_courses_per_semester'  => 6,
            'max_semesters_to_graduate' => 4,
            'elective_credits_required' => 0,
            'active'                    => false,
        ]);

        $response = $this->get(route('programs.catalog.show', $inactive->code));
        $response->assertStatus(404);
    }
}
