<?php

namespace Tests\Feature\Portal;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\OfferingStaffRole;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CoursePrerequisite;
use App\Models\Enrollment;
use App\Models\OfferingStaff;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicCourseDetailTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private Course $prereqA;
    private Course $prereqB;
    private Program $programA;
    private Program $programB;
    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->buildFixture();
    }

    private function buildFixture(): void
    {
        // Prerequisite courses
        $this->prereqA = Course::query()->create([
            'code'          => 'PRE101',
            'title'         => 'Prerequisite Alpha',
            'credit_hours'  => 2,
            'is_standalone' => false,
            'is_free'       => true,
            'active'        => true,
        ]);
        $this->prereqB = Course::query()->create([
            'code'          => 'PRE102',
            'title'         => 'Prerequisite Beta',
            'credit_hours'  => 2,
            'is_standalone' => false,
            'is_free'       => true,
            'active'        => true,
        ]);

        // Primary course
        $this->course = Course::query()->create([
            'code'              => 'CORE201',
            'title'             => 'Core Course Detail',
            'description'       => 'A detailed course for testing the public detail page.',
            'credit_hours'      => 3,
            'is_standalone'     => false,
            'is_free'           => false,
            'active'            => true,
            'default_price_usd' => 5000,   // $50.00
            'default_price_egp' => 150000, // 1500 EGP
        ]);

        // Attach prerequisites via the model (triggers HasUlids for the pivot id)
        CoursePrerequisite::query()->create([
            'course_id'       => $this->course->id,
            'prerequisite_id' => $this->prereqA->id,
        ]);
        CoursePrerequisite::query()->create([
            'course_id'       => $this->course->id,
            'prerequisite_id' => $this->prereqB->id,
        ]);

        // Program A — year 1, required
        $this->programA = Program::query()->create([
            'code'                      => 'PROG-A',
            'name'                      => 'Program Alpha',
            'type'                      => ProgramType::Certificate,
            'active'                    => true,
            'passing_threshold'         => 60.0,
            'max_credits_per_semester'  => 18,
            'max_courses_per_semester'  => 6,
            'max_semesters_to_graduate' => 4,
            'elective_credits_required' => 0,
        ]);
        ProgramCourse::query()->create([
            'program_id'  => $this->programA->id,
            'course_id'   => $this->course->id,
            'requirement' => RequirementType::Required,
            'year_level'  => 1,
        ]);

        // Program B — year 2, elective
        $this->programB = Program::query()->create([
            'code'                      => 'PROG-B',
            'name'                      => 'Program Beta',
            'type'                      => ProgramType::Diploma,
            'active'                    => true,
            'passing_threshold'         => 60.0,
            'max_credits_per_semester'  => 18,
            'max_courses_per_semester'  => 6,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
        ]);
        ProgramCourse::query()->create([
            'program_id'  => $this->programB->id,
            'course_id'   => $this->course->id,
            'requirement' => RequirementType::Elective,
            'year_level'  => 2,
        ]);

        // Open offering
        $this->offering = CourseOffering::query()->create([
            'course_id'     => $this->course->id,
            'status'        => OfferingStatus::Open,
            'mode'          => OfferingMode::Cohort,
            'seat_capacity' => 20,
            'start_date'    => now()->addWeek(),
            'end_date'      => now()->addMonths(3),
        ]);
    }

    // ── Gate 1: Guest 200 ────────────────────────────────────────────────

    #[Test]
    public function guest_can_view_public_course_detail(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee($this->course->title);
    }

    #[Test]
    public function page_shows_course_description(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee('A detailed course for testing the public detail page.');
    }

    #[Test]
    public function unknown_code_returns_404(): void
    {
        $response = $this->get(route('courses.public.show', 'DOES-NOT-EXIST'));

        $response->assertStatus(404);
    }

    #[Test]
    public function inactive_course_returns_404(): void
    {
        $inactive = Course::query()->create([
            'code'          => 'INACTIVE201',
            'title'         => 'Inactive Course',
            'credit_hours'  => 3,
            'is_standalone' => false,
            'is_free'       => true,
            'active'        => false,
        ]);

        $response = $this->get(route('courses.public.show', $inactive->code));

        $response->assertStatus(404);
    }

    // ── Gate 2: Prerequisites ────────────────────────────────────────────

    #[Test]
    public function course_with_prerequisites_shows_linked_list(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee($this->prereqA->code);
        $response->assertSee($this->prereqA->title);
        $response->assertSee($this->prereqB->code);
        $response->assertSee($this->prereqB->title);
    }

    #[Test]
    public function prerequisite_links_resolve_to_200(): void
    {
        $responseA = $this->get(route('courses.public.show', $this->prereqA->code));
        $responseA->assertStatus(200);

        $responseB = $this->get(route('courses.public.show', $this->prereqB->code));
        $responseB->assertStatus(200);
    }

    #[Test]
    public function prerequisite_links_point_to_correct_url(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee(route('courses.public.show', $this->prereqA->code), false);
        $response->assertSee(route('courses.public.show', $this->prereqB->code), false);
    }

    // ── Gate 3: Course in two programs ──────────────────────────────────

    #[Test]
    public function course_in_two_programs_lists_both(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee('Program Alpha');
        $response->assertSee('Program Beta');
    }

    #[Test]
    public function program_listings_show_requirement_types(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee(__('courses.required'));
        $response->assertSee(__('courses.elective'));
    }

    #[Test]
    public function program_listings_show_year_levels(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        // Year labels should be present for both programs
        $response->assertSee(__('courses.year_level', ['year' => 1]));
        $response->assertSee(__('courses.year_level', ['year' => 2]));
    }

    #[Test]
    public function program_links_point_to_catalog_show(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee(route('programs.catalog.show', $this->programA->code), false);
        $response->assertSee(route('programs.catalog.show', $this->programB->code), false);
    }

    // ── Gate 4: Auth-aware CTAs ──────────────────────────────────────────

    #[Test]
    public function guest_sees_sign_in_to_enroll_prompt(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee(__('courses.sign_in_to_enroll'));
    }

    #[Test]
    public function guest_does_not_see_enroll_form(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        // Guest should not see the direct enroll button (no form POST to enrollments.store)
        $content = $response->getContent();
        $this->assertStringNotContainsString(__('courses.enroll'), $content,
            'Guest must not see the direct Enroll button'
        );
    }

    #[Test]
    public function authenticated_student_sees_enroll_button(): void
    {
        $student = User::query()->create([
            'first_name'        => 'Test',
            'last_name'         => 'Student',
            'email'             => 'tstudent@example.com',
            'password_hash'     => bcrypt('password'),
            'email_verified'    => true,
        ]);
        $this->actingAs($student);

        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee(__('courses.enroll'));
        $response->assertDontSee(__('courses.sign_in_hint'));
    }

    #[Test]
    public function already_enrolled_student_sees_enrolled_state(): void
    {
        $student = User::query()->create([
            'first_name'     => 'Enrolled',
            'last_name'      => 'Student',
            'email'          => 'enrolled@example.com',
            'password_hash'  => bcrypt('password'),
            'email_verified' => true,
        ]);
        Enrollment::query()->create([
            'student_id'  => $student->id,
            'offering_id' => $this->offering->id,
            'status'      => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($student);
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee(__('courses.already_enrolled'));
    }

    #[Test]
    public function waitlisted_student_sees_waitlisted_state(): void
    {
        $student = User::query()->create([
            'first_name'     => 'Waiting',
            'last_name'      => 'Student',
            'email'          => 'waiting@example.com',
            'password_hash'  => bcrypt('password'),
            'email_verified' => true,
        ]);
        Enrollment::query()->create([
            'student_id'  => $student->id,
            'offering_id' => $this->offering->id,
            'status'      => EnrollmentStatus::Waitlisted,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($student);
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee(__('courses.already_waitlisted'));
    }

    // ── Gate 5: No raw minor units ───────────────────────────────────────

    #[Test]
    public function page_does_not_output_raw_minor_price(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        // Raw minor integers must not appear as bare numbers in output
        $content = $response->getContent();
        $this->assertStringNotContainsString('>5000<', $content,
            'USD price must not appear as raw minor units'
        );
        $this->assertStringNotContainsString('>150000<', $content,
            'EGP price must not appear as raw minor units'
        );
    }

    #[Test]
    public function page_shows_formatted_price_via_money_component(): void
    {
        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        // The <x-money> component renders with class spims-money
        $response->assertSee('spims-money', false);
    }

    // ── Instructor display ───────────────────────────────────────────────

    #[Test]
    public function open_offering_instructors_are_listed(): void
    {
        $instructor = User::query()->create([
            'first_name'     => 'Father',
            'last_name'      => 'Athanasius',
            'email'          => 'fathanasius@example.com',
            'password_hash'  => bcrypt('password'),
            'email_verified' => true,
        ]);
        OfferingStaff::query()->create([
            'offering_id' => $this->offering->id,
            'user_id'     => $instructor->id,
            'role'        => OfferingStaffRole::Instructor,
        ]);

        $response = $this->get(route('courses.public.show', $this->course->code));

        $response->assertStatus(200);
        $response->assertSee('Father');
    }

    // ── Three-locale lang parity ─────────────────────────────────────────

    #[Test]
    public function all_three_locales_have_courses_keys(): void
    {
        $enKeys = array_keys(require base_path('lang/en/courses.php'));
        $arKeys = array_keys(require base_path('lang/ar/courses.php'));
        $frKeys = array_keys(require base_path('lang/fr/courses.php'));

        sort($enKeys);
        sort($arKeys);
        sort($frKeys);

        $this->assertSame($enKeys, $arKeys, 'AR lang/courses.php missing keys vs EN');
        $this->assertSame($enKeys, $frKeys, 'FR lang/courses.php missing keys vs EN');
    }
}
