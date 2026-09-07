<?php

namespace Tests\Feature\Offerings;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Enums\VideoProvider;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentBuilderEditFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function instructor_can_update_item_with_youtube_watch_link_via_video_url(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $instructor] = $this->staffedOffering();

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Video->value,
            'title' => 'Lecture recording',
            'vimeo_id' => '123456789',
        ])->assertRedirect();

        $item = ContentItem::query()->where('title', 'Lecture recording')->firstOrFail();
        $this->assertSame(VideoProvider::Vimeo, $item->video_provider);
        $this->assertSame('123456789', $item->vimeo_id);

        $html = $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->assertOk()
            ->getContent();

        $updateAction = e(route('admin.content-items.update', $item));
        $this->assertMatchesRegularExpression(
            '#<form[^>]*action="'.preg_quote($updateAction, '#').'"[^>]*>.*?name="video_url".*?</form>#s',
            $html,
            'Teach content HTML must include name="video_url" inside an existing item update form.',
        );

        $this->actingAs($instructor)
            ->from(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->put(route('admin.content-items.update', $item), [
                'type' => ContentItemType::Video->value,
                'title' => 'Lecture recording',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'vimeo_id' => $item->vimeo_id,
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(VideoProvider::YouTube, $item->video_provider);
        $this->assertSame('dQw4w9WgXcQ', $item->vimeo_id);
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: User}
     */
    private function staffedOffering(): array
    {
        $course = Course::query()->create([
            'code' => 'EDT1',
            'title' => 'Edit Parity',
            'credit_hours' => 1,
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
            'title' => 'Week 1',
            'order' => 1,
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $week, $instructor];
    }
}
