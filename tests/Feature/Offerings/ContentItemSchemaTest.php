<?php

namespace Tests\Feature\Offerings;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\VideoProvider;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentItemSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function existing_style_creates_are_published_and_vimeo_is_backfilled(): void
    {
        $week = $this->week();

        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Video,
            'title' => 'Legacy video',
            'order' => 1,
            'vimeo_id' => '555',
        ]);

        $item->refresh();
        $this->assertTrue($item->published);
        $this->assertNotNull($item->published_at);
        $this->assertTrue($item->isPublished());
        $this->assertSame(VideoProvider::Vimeo, $item->video_provider);
    }

    #[Test]
    public function published_scope_excludes_drafts(): void
    {
        $week = $this->week();
        $week->items()->create([
            'type' => ContentItemType::Text,
            'title' => 'Live',
            'order' => 1,
            'body' => 'Hi',
            'published' => true,
        ]);
        $week->items()->create([
            'type' => ContentItemType::Text,
            'title' => 'Draft',
            'order' => 2,
            'body' => 'Soon',
            'published' => false,
            'published_at' => null,
        ]);

        $titles = ContentItem::query()->published()->orderBy('order')->pluck('title')->all();
        $this->assertSame(['Live'], $titles);
    }

    #[Test]
    public function new_unpublished_flag_is_respected(): void
    {
        $week = $this->week();
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Text,
            'title' => 'Hidden',
            'order' => 1,
            'published' => false,
        ]);

        $this->assertFalse($item->fresh()->isPublished());
        $this->assertFalse(ContentItem::query()->published()->whereKey($item->id)->exists());
    }

    private function week(): Week
    {
        $course = Course::query()->create([
            'code' => 'SCH1',
            'title' => 'Schema',
            'credit_hours' => 1,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
        ]);

        return Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'One',
            'order' => 1,
        ]);
    }
}
