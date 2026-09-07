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
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentCatalogPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_sees_week_one_embeds_and_not_later_weeks(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/week/catalog.pdf', '%PDF-1.4');

        $offering = $this->offering();
        $week1 = $offering->weeks->firstWhere('number', 1);
        $week2 = $offering->weeks->firstWhere('number', 2);

        $week1->items()->create([
            'type' => ContentItemType::Video,
            'title' => 'Welcome video',
            'order' => 1,
            'vimeo_id' => '12345',
            'video_provider' => VideoProvider::YouTube,
            'published' => true,
        ]);
        $pdf = $week1->items()->create([
            'type' => ContentItemType::Reading,
            'title' => 'Syllabus PDF',
            'order' => 2,
            'file_url' => 'uploads/week/catalog.pdf',
            'published' => true,
        ]);
        $week1->items()->create([
            'type' => ContentItemType::Text,
            'title' => 'Draft week one',
            'order' => 3,
            'body' => 'hidden',
            'published' => false,
        ]);
        $week2Item = $week2->items()->create([
            'type' => ContentItemType::Reading,
            'title' => 'Week two secret',
            'order' => 1,
            'file_url' => 'uploads/week/catalog.pdf',
            'published' => true,
        ]);

        $this->get(route('offerings.preview', $offering))
            ->assertOk()
            ->assertSee('Welcome video')
            ->assertSee('youtube-nocookie.com/embed/12345', false)
            ->assertSee('Syllabus PDF')
            ->assertSee(route('offerings.preview.item.file', [$offering, $pdf]), false)
            ->assertDontSee('Draft week one')
            ->assertSee('Next')
            ->assertDontSee('Week two secret');

        $this->getJson(route('api.offerings.preview', $offering))
            ->assertOk()
            ->assertJsonPath('week_one.0.title', 'Welcome video')
            ->assertJsonMissing(['file_url' => 'uploads/week/catalog.pdf']);

        $this->get(route('offerings.preview.item.file', [$offering, $pdf]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(route('offerings.preview.item.file', [$offering, $week2Item]))
            ->assertNotFound();
    }

    private function offering(): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'PRE1',
            'title' => 'Preview Course',
            'credit_hours' => 1,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
        ]);
        Week::query()->create(['offering_id' => $offering->id, 'number' => 1, 'title' => 'Intro', 'order' => 1]);
        Week::query()->create(['offering_id' => $offering->id, 'number' => 2, 'title' => 'Next', 'order' => 2]);

        return $offering->fresh(['weeks.items', 'course']);
    }
}
