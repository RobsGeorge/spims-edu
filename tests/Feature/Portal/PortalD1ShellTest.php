<?php

namespace Tests\Feature\Portal;

use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\OfferingStaff;
use App\Models\User;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalD1ShellTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_layout_exposes_sidebar_and_bottom_nav(): void
    {
        $this->seed(ThemeSeeder::class);
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('app-shell', false)
            ->assertSee('app-sidebar', false)
            ->assertSee('app-bottom-nav', false)
            ->assertSee(__('hubs.nav_learning'))
            ->assertSee(__('hubs.nav_more'));
    }

    #[Test]
    public function instructor_sees_teach_nav_and_can_open_teach_hub(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $course = Course::query()->create([
            'code' => 'TEACH1',
            'title' => 'Teach Course',
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

        $this->actingAs($instructor)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('hubs.nav_teach'));

        $this->actingAs($instructor)->get(route('teach.index'))
            ->assertOk()
            ->assertSee($course->code)
            ->assertSee(__('teach.title'));

        $this->actingAs($instructor)->get(route('teach.show', $offering))
            ->assertOk()
            ->assertSee(__('teach.tab_content'))
            ->assertSee(__('teach.tab_gradebook'));
    }

    #[Test]
    public function student_cannot_open_teach_hub(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('teach.index'))->assertForbidden();
    }

    #[Test]
    public function offering_admin_show_includes_workspace_tabs(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $offering = CourseOffering::query()->first();
        $this->assertNotNull($offering);

        $this->actingAs($sa)->get(route('admin.offerings.show', $offering))
            ->assertOk()
            ->assertSee(__('teach.tab_assessments'))
            ->assertSee(__('teach.workspace'));
    }
}
