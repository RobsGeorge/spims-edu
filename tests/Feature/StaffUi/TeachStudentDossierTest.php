<?php

namespace Tests\Feature\StaffUi;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeachStudentDossierTest extends TestCase
{
    use RefreshDatabase;

    private function offering(string $code = 'DOS1'): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => 'Dossier Course '.$code,
            'credit_hours' => 3,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => OfferingStatus::Open,
        ])->fresh('course');
    }

    private function enroll(User $student, CourseOffering $offering): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);
    }

    #[Test]
    public function staffed_instructor_sees_student_dossier(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Mariam',
            'last_name' => 'Dossier',
            'email' => 'mariam.dossier@example.test',
        ]);
        $offering = $this->offering();
        $this->staffOffering($instructor, $offering);
        $this->enroll($student, $offering);

        $this->actingAs($instructor)
            ->get(route('teach.students.show', [$offering, $student]))
            ->assertOk()
            ->assertSee('Mariam')
            ->assertSee('Dossier')
            ->assertSee('mariam.dossier@example.test')
            ->assertSee(__('teach.profile'))
            ->assertSee(__('teach.enrollment'))
            ->assertSee(__('teach.grades'))
            ->assertSee(__('teach.attendance_percent'))
            ->assertSee(__('teach.back_to_roster'))
            ->assertSee(__('teach.open_notes'))
            ->assertSee(route('teach.show', ['offering' => $offering, 'tab' => 'roster']), false)
            ->assertSee(route('teach.completion.show', ['offering' => $offering, 'student_id' => $student->id]), false);

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'roster']))
            ->assertOk()
            ->assertSee(__('teach.view_dossier'))
            ->assertSee(route('teach.students.show', [$offering, $student]), false)
            ->assertSee(__('completion.notes'));
    }

    #[Test]
    public function instructor_not_staffed_on_offering_is_forbidden(): void
    {
        $this->seed(ThemeSeeder::class);
        $staffed = User::factory()->withRole(RoleType::Instructor)->create();
        $outsider = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offeringA = $this->offering('DOSA');
        $offeringB = $this->offering('DOSB');
        $this->staffOffering($staffed, $offeringA);
        $this->staffOffering($outsider, $offeringB);
        $this->enroll($student, $offeringA);

        $this->actingAs($outsider)
            ->get(route('teach.students.show', [$offeringA, $student]))
            ->assertForbidden();
    }

    #[Test]
    public function student_is_forbidden_from_dossier(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offering('DOSS');
        $this->staffOffering($instructor, $offering);
        $this->enroll($student, $offering);

        $this->actingAs($student)
            ->get(route('teach.students.show', [$offering, $student]))
            ->assertForbidden();
    }

    #[Test]
    public function unenrolled_student_is_not_found(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $enrolled = User::factory()->withRole(RoleType::Student)->create();
        $stranger = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offering('DOS4');
        $this->staffOffering($instructor, $offering);
        $this->enroll($enrolled, $offering);

        $this->actingAs($instructor)
            ->get(route('teach.students.show', [$offering, $stranger]))
            ->assertNotFound();
    }
}
