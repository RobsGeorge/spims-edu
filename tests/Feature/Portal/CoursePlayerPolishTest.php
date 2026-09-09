<?php

namespace Tests\Feature\Portal;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * W3-a: Course Player Polish
 * Tests progress bar on offering view, item-count chip in week-nav, and prev/next navigation on item view.
 */
class CoursePlayerPolishTest extends TestCase
{
    use RefreshDatabase;

    private function makeOfferingWithWeekAndItems(): array
    {
        $course = Course::query()->create([
            'code' => 'W3PLY',
            'title' => 'W3 Player Polish',
            'credit_hours' => 3,
            'is_standalone' => true,
            'is_free' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Introduction',
            'order' => 1,
        ]);

        $item1 = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Video,
            'title' => 'Welcome Video',
            'order' => 1,
            'vimeo_id' => '111111',
        ]);

        $item2 = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Reading,
            'title' => 'Reading Material',
            'order' => 2,
        ]);

        $student = User::factory()->withRole(RoleType::Student)->create();

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 50,
        ]);

        return compact('offering', 'week', 'item1', 'item2', 'student', 'enrollment');
    }

    #[Test]
    public function offering_view_shows_progress_bar_with_percentage(): void
    {
        ['offering' => $offering, 'student' => $student] = $this->makeOfferingWithWeekAndItems();

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee('progress-bar', false)
            ->assertSee('role="progressbar"', false)
            ->assertSee(__('learn.complete'));
    }

    #[Test]
    public function week_nav_shows_item_count_chip_for_each_week(): void
    {
        ['offering' => $offering, 'student' => $student] = $this->makeOfferingWithWeekAndItems();

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee('text-bg-info', false);
    }

    #[Test]
    public function item_view_shows_prev_and_next_navigation(): void
    {
        ['offering' => $offering, 'week' => $week, 'item1' => $item1, 'item2' => $item2, 'student' => $student] = $this->makeOfferingWithWeekAndItems();

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $item2]))
            ->assertOk()
            ->assertSee(__('learn.prev_item'))
            ->assertSee(__('learn.next_item'));
    }

    #[Test]
    public function item_view_first_item_has_disabled_prev(): void
    {
        ['offering' => $offering, 'item1' => $item1, 'student' => $student] = $this->makeOfferingWithWeekAndItems();

        $response = $this->actingAs($student)
            ->get(route('learn.item', [$offering, $item1]))
            ->assertOk();

        $response->assertSee(__('learn.prev_item'));
        $response->assertSee(__('learn.next_item'));
        // First item: prev link is a disabled span
        $response->assertSee('aria-disabled="true"', false);
    }

    #[Test]
    public function all_three_locales_have_new_learn_keys(): void
    {
        $keys = ['complete', 'prev_item', 'next_item'];

        foreach (['en', 'ar', 'fr'] as $locale) {
            $translations = require base_path("lang/{$locale}/learn.php");
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $translations, "learn.{$key} missing from {$locale} locale");
            }
        }
    }
}
