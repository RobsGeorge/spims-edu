<?php

namespace Tests\Feature\Offerings;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentReorderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function instructor_can_reorder_and_move_items_across_weeks(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week1, $week2, $instructor, $a, $b, $c] = $this->bundle();

        $this->actingAs($instructor)
            ->get(route('teach.show', $offering))
            ->assertOk()
            ->assertSee(__('offerings.move_up'));

        $this->actingAs($instructor)->post(route('admin.content-items.move-up', $c))->assertRedirect();
        $this->assertSame(['Item-A', 'Item-C', 'Item-B'], $this->titles($week1));

        $this->actingAs($instructor)->post(route('admin.content-items.move-down', $c))->assertRedirect();
        $this->assertSame(['Item-A', 'Item-B', 'Item-C'], $this->titles($week1));

        $this->actingAs($instructor)->post(route('admin.content-items.move-down', $c))->assertRedirect();
        $this->assertSame(['Item-A', 'Item-B', 'Item-C'], $this->titles($week1));

        $this->actingAs($instructor)
            ->post(route('admin.content-items.move', $a), ['week_id' => $week2->id])
            ->assertRedirect();
        $this->assertSame(['Item-B', 'Item-C'], $this->titles($week1));
        $this->assertSame(['Item-A'], $this->titles($week2));
        $this->assertNotNull(AuditLog::query()->where('action', 'offerings.move_content')->first());
        $this->assertNotNull(AuditLog::query()->where('action', 'offerings.reorder_content')->first());

        $other = CourseOffering::query()->create([
            'course_id' => $offering->course_id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);
        $foreign = Week::query()->create([
            'offering_id' => $other->id,
            'number' => 1,
            'title' => 'Foreign',
            'order' => 1,
        ]);

        $this->actingAs($instructor)
            ->from(route('teach.show', $offering))
            ->post(route('admin.content-items.move', $b), ['week_id' => $foreign->id])
            ->assertRedirect()
            ->assertSessionHasErrors('week_id');
    }

    #[Test]
    public function student_sees_new_order_and_outsider_cannot_reorder(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week1, , $instructor, $a, $b, $c] = $this->bundle();
        $student = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);
        foreach ([$a, $b, $c] as $item) {
            $this->actingAs($instructor)->post(route('admin.content-items.publish', $item));
        }

        $this->actingAs($instructor)->post(route('admin.content-items.move-up', $c));

        $html = $this->actingAs($student)->get(route('learn.offering', $offering))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'Item-C'), strpos($html, 'Item-A'));
        $this->assertLessThan(strpos($html, 'Item-B'), strpos($html, 'Item-C'));

        $outsider = User::factory()->withRole(RoleType::Instructor)->create();
        $this->actingAs($outsider)->post(route('admin.content-items.move-up', $c))->assertForbidden();
    }

    /**
     * @return list<string>
     */
    private function titles(Week $week): array
    {
        return ContentItem::query()->where('week_id', $week->id)->orderBy('order')->pluck('title')->all();
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: Week, 3: User, 4: ContentItem, 5: ContentItem, 6: ContentItem}
     */
    private function bundle(): array
    {
        $course = Course::query()->create([
            'code' => 'ORD1',
            'title' => 'Order',
            'credit_hours' => 1,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);
        $week1 = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'W1',
            'order' => 1,
        ]);
        $week2 = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'W2',
            'order' => 2,
        ]);
        $a = $week1->items()->create(['type' => ContentItemType::Text, 'title' => 'Item-A', 'order' => 1, 'published' => false]);
        $b = $week1->items()->create(['type' => ContentItemType::Text, 'title' => 'Item-B', 'order' => 2, 'published' => false]);
        $c = $week1->items()->create(['type' => ContentItemType::Text, 'title' => 'Item-C', 'order' => 3, 'published' => false]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $week1, $week2, $instructor, $a, $b, $c];
    }
}
