<?php

namespace Tests\Feature\Learn;

use App\Enums\ContentItemType;
use App\Enums\RoleType;
use App\Models\ContentItem;
use App\Models\User;
use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Completion\CompletionFixtures;
use Tests\TestCase;

class LearnPlayerTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function offering_view_shows_all_published_weeks_in_accordion(): void
    {
        $offering = $this->offering('LP1');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $week1 = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Introduction to the course',
            'order' => 1,
        ]);
        $week2 = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'Core concepts',
            'order' => 2,
        ]);

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee('Introduction to the course')
            ->assertSee('Core concepts');
    }

    #[Test]
    public function offering_view_shows_item_titles_for_active_week(): void
    {
        $offering = $this->offering('LP2');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Getting started',
            'order' => 1,
        ]);
        ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Reading,
            'title' => 'Welcome reading',
            'order' => 1,
            'status' => 'PUBLISHED',
        ]);
        ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Text,
            'title' => 'Course overview',
            'order' => 2,
            'status' => 'PUBLISHED',
        ]);

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee('Welcome reading')
            ->assertSee('Course overview');
    }

    #[Test]
    public function offering_view_shows_open_week_link_for_unlocked_week(): void
    {
        $offering = $this->offering('LP3');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week one',
            'order' => 1,
        ]);
        ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Text,
            'title' => 'Intro text',
            'order' => 1,
            'status' => 'PUBLISHED',
        ]);

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee(route('learn.week', [$offering, $week]), false);
    }

    #[Test]
    public function week_view_shows_items_with_completion_badges(): void
    {
        $offering = $this->offering('LP4');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $enrollment = $this->enroll($student, $offering);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week one',
            'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Text,
            'title' => 'Lesson text',
            'order' => 1,
            'status' => 'PUBLISHED',
        ]);

        // Mark item complete
        \App\Models\EnrollmentItemCompletion::query()->create([
            'enrollment_id' => $enrollment->id,
            'content_item_id' => $item->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('learn.week', [$offering, $week]))
            ->assertOk()
            ->assertSee('Lesson text')
            ->assertSee(__('learn.completed'));
    }

    #[Test]
    public function item_can_be_marked_complete_via_post(): void
    {
        $offering = $this->offering('LP5');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week one',
            'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Reading,
            'title' => 'A reading',
            'order' => 1,
            'status' => 'PUBLISHED',
        ]);

        $this->actingAs($student)
            ->post(route('learn.item.complete', [$offering, $item]))
            ->assertRedirect(route('learn.item', [$offering, $item]));
    }

    #[Test]
    public function offering_view_shows_locked_state_for_future_unlock_week(): void
    {
        $offering = $this->offering('LP6');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week one',
            'order' => 1,
        ]);
        // Week 2 unlocks in the future → should appear locked
        Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'Week two — gated',
            'order' => 2,
            'unlock_date' => now()->addDays(7),
        ]);

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee('Week two — gated')
            ->assertSee(__('learn.locked'));
    }
}
