<?php

namespace Tests\Feature\Portal;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\OfferingStaff;
use App\Models\User;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalD4TeachTest extends TestCase
{
    use RefreshDatabase;

    private function staffedOffering(User $instructor): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'D4TCH',
            'title' => 'Teach Hub Course',
            'credit_hours' => 3,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => OfferingStatus::Open,
        ]);
        OfferingStaff::query()->create([
            'offering_id' => $offering->id,
            'user_id' => $instructor->id,
            'role' => OfferingStaffRole::Instructor,
        ]);

        return $offering->fresh('course');
    }

    #[Test]
    public function instructor_can_post_announcement_with_audit(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $offering = $this->staffedOffering($instructor);

        $this->actingAs($instructor)
            ->post(route('teach.announcements.store', $offering), [
                'title' => 'Week 2 open',
                'body' => 'Please watch the new lecture.',
            ])
            ->assertRedirect(route('teach.show', ['offering' => $offering, 'tab' => 'announcements']));

        $this->assertDatabaseHas('announcements', [
            'offering_id' => $offering->id,
            'title' => 'Week 2 open',
            'author_id' => $instructor->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'announcement.create',
            'actor_id' => $instructor->id,
        ]);
    }

    #[Test]
    public function teach_roster_lists_enrolled_students(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Roster',
            'last_name' => 'Student',
        ]);
        $offering = $this->staffedOffering($instructor);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'roster']))
            ->assertOk()
            ->assertSee('Roster Student')
            ->assertSee(__('teach.students'));
    }

    #[Test]
    public function gradebook_shows_consequence_confirm_dialogs(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $offering = CourseOffering::query()->firstOrFail();

        $this->actingAs($sa)->get(route('admin.gradebook.show', $offering))
            ->assertOk()
            ->assertSee('lockGradesModal', false)
            ->assertSee(__('teach.lock_confirm_title'))
            ->assertSee(__('teach.reopen_confirm_title'));
    }

    #[Test]
    public function ta_cannot_access_unassigned_offering_teach_workspace(): void
    {
        $this->seed(ThemeSeeder::class);
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $other = User::factory()->withRole(RoleType::Instructor)->create();
        $offering = $this->staffedOffering($other);

        $this->actingAs($ta)->get(route('teach.show', $offering))->assertForbidden();
    }
}
