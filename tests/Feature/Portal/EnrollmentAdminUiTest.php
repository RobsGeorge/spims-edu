<?php

namespace Tests\Feature\Portal;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnrollmentAdminUiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function financial_hold_form_forbidden_for_student_and_ok_for_administrative_admin(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $target = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->from(route('dashboard'))
            ->post(route('admin.enrollments.financial-hold', $target), ['held' => true])
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('admin.enrollments.index'))
            ->post(route('admin.enrollments.financial-hold', $target), ['held' => true])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee(__('enrollment.financial_hold_label'))
            ->assertSee(__('enrollment.release_hold'));
    }

    #[Test]
    public function waitlist_and_user_admin_pages_are_ok_for_allowed_roles(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $academic = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->openOffering();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Waitlisted,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.enrollments.index'))
            ->assertOk()
            ->assertSee(__('enrollment.override_register'))
            ->assertSee(__('enrollment.place_hold'));

        $this->actingAs($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee(__('enrollment.financial_hold_label'))
            ->assertSee(__('enrollment.override_register'));

        $this->actingAs($admin)
            ->get(route('admin.offerings.show', $offering))
            ->assertOk()
            ->assertSee(__('enrollment.waitlist'));

        $this->actingAs($academic)
            ->get(route('admin.offerings.show', $offering))
            ->assertOk()
            ->assertSee(__('enrollment.waitlist'));

        foreach ([$admin, $academic] as $actor) {
            $this->actingAs($actor)
                ->get(route('admin.enrollments.waitlist', $offering))
                ->assertOk()
                ->assertSee($student->email)
                ->assertSee(__('enrollment.waitlist_promote_note'));
        }

        $this->actingAs($student)
            ->get(route('admin.enrollments.waitlist', $offering))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.enrollments.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.users.show', $student))
            ->assertForbidden();

        $this->actingAs($academic)
            ->get(route('admin.enrollments.index'))
            ->assertForbidden();
    }

    #[Test]
    public function student_cannot_drop_another_students_enrollment_even_with_register_permission(): void
    {
        $owner = User::factory()->withRole(RoleType::Student)->create();
        $other = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->openOffering();
        $enrollment = Enrollment::query()->create([
            'student_id' => $owner->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($other)
            ->post(route('enrollments.drop', $enrollment))
            ->assertSessionHasErrors('enrollment');
    }

    private function openOffering(): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'WAIT1',
            'title' => 'Waitlist course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
    }
}
