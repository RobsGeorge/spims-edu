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

class ContentBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function instructor_can_add_edit_delete_and_publish_from_both_uis(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $instructor] = $this->staffedOffering();

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->assertOk()
            ->assertSee(__('offerings.add_item'))
            ->assertSee(__('offerings.item_starts_draft'));

        $this->actingAs($instructor)
            ->get(route('admin.offerings.show', $offering))
            ->assertOk()
            ->assertSee(__('offerings.add_item'));

        $this->actingAs($instructor)
            ->from(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Text->value,
                'title' => 'Draft notes',
                'body' => 'Hidden for now',
            ])
            ->assertRedirect();

        $item = ContentItem::query()->where('title', 'Draft notes')->first();
        $this->assertNotNull($item);
        $this->assertFalse($item->published);
        $this->assertNotNull(AuditLog::query()->where('action', 'offerings.add_content')->first());

        $this->actingAs($instructor)
            ->from(route('admin.offerings.show', $offering))
            ->put(route('admin.content-items.update', $item), [
                'title' => 'Draft notes revised',
                'type' => ContentItemType::Text->value,
                'body' => 'Still hidden',
            ])
            ->assertRedirect();

        $this->assertSame('Draft notes revised', $item->fresh()->title);
        $this->assertNotNull(AuditLog::query()->where('action', 'offerings.update_content')->first());

        $this->actingAs($instructor)
            ->post(route('admin.content-items.publish', $item))
            ->assertRedirect();
        $this->assertTrue($item->fresh()->isPublished());
        $this->assertNotNull(AuditLog::query()->where('action', 'offerings.publish_content')->first());

        $this->actingAs($instructor)
            ->post(route('admin.content-items.unpublish', $item))
            ->assertRedirect();
        $this->assertFalse($item->fresh()->isPublished());

        $this->actingAs($instructor)
            ->delete(route('admin.content-items.destroy', $item))
            ->assertRedirect();
        $this->assertNull(ContentItem::query()->find($item->id));
        $this->assertNotNull(AuditLog::query()->where('action', 'offerings.delete_content')->first());
    }

    #[Test]
    public function ta_can_mutate_content_outsider_and_student_cannot(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $instructor] = $this->staffedOffering();
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $offering, \App\Enums\OfferingStaffRole::Ta);
        $outsider = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($ta)
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Text->value,
                'title' => 'TA note',
            ])
            ->assertRedirect();

        $item = ContentItem::query()->where('title', 'TA note')->first();
        $this->assertNotNull($item);

        $this->actingAs($outsider)
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Text->value,
                'title' => 'Nope',
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->put(route('admin.content-items.update', $item), ['title' => 'Hack'])
            ->assertForbidden();

        $this->actingAs($instructor)
            ->get(route('teach.show', $offering))
            ->assertOk();
    }

    #[Test]
    public function students_do_not_see_drafts_until_published(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $instructor] = $this->staffedOffering();
        $student = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Text->value,
            'title' => 'Secret draft',
            'body' => 'Not yet',
        ]);
        $item = ContentItem::query()->where('title', 'Secret draft')->firstOrFail();

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertDontSee('Secret draft');

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $item]))
            ->assertNotFound();

        $this->actingAs($instructor)->post(route('admin.content-items.publish', $item));

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee('Secret draft');

        $this->actingAs($instructor)->post(route('admin.content-items.unpublish', $item));

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertDontSee('Secret draft');
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: User}
     */
    private function staffedOffering(): array
    {
        $course = Course::query()->create([
            'code' => 'BLD1',
            'title' => 'Builder',
            'credit_hours' => 2,
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
            'title' => 'Opening Week',
            'order' => 1,
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $week, $instructor];
    }
}
