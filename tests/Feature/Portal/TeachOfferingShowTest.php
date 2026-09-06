<?php

namespace Tests\Feature\Portal;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeachOfferingShowTest extends TestCase
{
    use RefreshDatabase;

    private function offeringWithWeek(): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'TH101',
            'title' => 'Teach Workspace Course',
            'credit_hours' => 3,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => OfferingStatus::Open,
        ]);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Opening Week',
            'order' => 1,
        ]);

        $week->items()->create([
            'type' => ContentItemType::Reading,
            'title' => 'Syllabus',
            'order' => 1,
            'body' => 'Read the syllabus.',
        ]);

        return $offering->fresh('course');
    }

    #[Test]
    public function staffed_instructor_can_open_teach_workspace_tabs(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $offering = $this->offeringWithWeek();
        $this->staffOffering($instructor, $offering);

        $this->actingAs($instructor)
            ->get(route('teach.show', $offering))
            ->assertOk()
            ->assertSee(__('teach.week_n', ['n' => 1]))
            ->assertSee('Opening Week')
            ->assertSee('1 '.__('teach.items'));

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->assertOk()
            ->assertSee(__('teach.tab_content'));

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'roster']))
            ->assertOk()
            ->assertSee(__('teach.tab_roster'));

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'announcements']))
            ->assertOk()
            ->assertSee(__('teach.tab_announcements'));
    }

    #[Test]
    public function staffed_instructor_can_open_attendance_sub_routes(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $offering = $this->offeringWithWeek();
        $this->staffOffering($instructor, $offering);

        $this->actingAs($instructor)
            ->get(route('teach.attendance.index', $offering))
            ->assertOk();

        $this->actingAs($instructor)
            ->get(route('teach.attendance.index', ['offering' => $offering, 'tab' => 'report']))
            ->assertOk();

        $this->actingAs($instructor)
            ->get(route('teach.attendance.roster.csv', $offering))
            ->assertOk();
    }

    #[Test]
    public function instructor_not_staffed_on_offering_is_forbidden(): void
    {
        $this->seed(ThemeSeeder::class);
        $staffed = User::factory()->withRole(RoleType::Instructor)->create();
        $outsider = User::factory()->withRole(RoleType::Instructor)->create();
        $offering = $this->offeringWithWeek();
        $this->staffOffering($staffed, $offering);

        $this->actingAs($outsider)
            ->get(route('teach.show', $offering))
            ->assertForbidden();
    }

    #[Test]
    public function student_is_forbidden_from_teach_workspace(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithWeek();
        $this->staffOffering($instructor, $offering);

        $this->actingAs($student)
            ->get(route('teach.show', $offering))
            ->assertForbidden();
    }
}
