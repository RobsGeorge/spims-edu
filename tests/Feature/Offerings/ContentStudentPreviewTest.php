<?php

namespace Tests\Feature\Offerings;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentStudentPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function instructor_previews_as_generic_student_without_writes(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week1, $week2, $instructor, $draft, $live] = $this->bundle();

        $this->actingAs($instructor)
            ->from(route('teach.show', $offering))
            ->post(route('offerings.preview.student', $offering))
            ->assertRedirect(route('learn.offering', $offering));

        $this->assertNotNull(AuditLog::query()->where('action', 'learning.student_preview')->first());

        $this->actingAs($instructor)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee(__('offerings.preview_banner'))
            ->assertSee($live->title)
            ->assertDontSee($draft->title);

        $this->actingAs($instructor)
            ->get(route('learn.week', [$offering, $week2]))
            ->assertOk()
            ->assertSee(__('learn.week_locked'));

        $this->actingAs($instructor)
            ->post(route('learn.item.complete', [$offering, $live]))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->post(route('courses.weeks.complete', [$offering, $week1]))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->post(route('offerings.preview.stop', $offering))
            ->assertRedirect();
    }

    #[Test]
    public function cohort_future_week_is_locked_in_preview(): void
    {
        $this->seed(ThemeSeeder::class);
        $course = Course::query()->create(['code' => 'PV2', 'title' => 'P', 'credit_hours' => 1, 'active' => true]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => OfferingStatus::Open,
        ]);
        $future = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'Later',
            'order' => 2,
            'unlock_date' => now()->addWeek(),
        ]);
        $future->items()->create([
            'type' => ContentItemType::Text,
            'title' => 'Future body',
            'order' => 1,
            'body' => 'secret',
            'published' => true,
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        $this->actingAs($instructor)->post(route('offerings.preview.student', $offering));
        $this->actingAs($instructor)
            ->get(route('learn.week', [$offering, $future]))
            ->assertOk()
            ->assertSee(__('learn.week_locked'))
            ->assertDontSee('secret');
    }

    #[Test]
    public function student_and_unstaffed_instructor_cannot_start_preview(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, , , $instructor] = $this->bundle();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $outsider = User::factory()->withRole(RoleType::Instructor)->create();

        $this->actingAs($student)
            ->post(route('offerings.preview.student', $offering))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('offerings.preview.student', $offering))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->get(route('teach.show', $offering))
            ->assertOk()
            ->assertSee(__('offerings.view_as_student'));
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: Week, 3: User, 4: \App\Models\ContentItem, 5: \App\Models\ContentItem}
     */
    private function bundle(): array
    {
        $course = Course::query()->create(['code' => 'PV1', 'title' => 'Preview', 'credit_hours' => 1, 'active' => true]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);
        $week1 = Week::query()->create(['offering_id' => $offering->id, 'number' => 1, 'title' => 'One', 'order' => 1]);
        $week2 = Week::query()->create(['offering_id' => $offering->id, 'number' => 2, 'title' => 'Two', 'order' => 2]);
        $live = $week1->items()->create([
            'type' => ContentItemType::Text,
            'title' => 'Published lesson',
            'order' => 1,
            'body' => 'Hello',
            'published' => true,
        ]);
        $draft = $week1->items()->create([
            'type' => ContentItemType::Text,
            'title' => 'Draft lesson',
            'order' => 2,
            'body' => 'Soon',
            'published' => false,
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $week1, $week2, $instructor, $draft, $live];
    }
}
