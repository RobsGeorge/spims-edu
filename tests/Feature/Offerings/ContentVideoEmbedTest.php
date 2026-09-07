<?php

namespace Tests\Feature\Offerings;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Enums\VideoProvider;
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

class ContentVideoEmbedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function instructor_can_paste_youtube_and_vimeo_urls(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $instructor] = $this->staffedOffering();

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Video->value,
            'title' => 'YT lecture',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertRedirect();

        $youtube = ContentItem::query()->where('title', 'YT lecture')->firstOrFail();
        $this->assertSame(VideoProvider::YouTube, $youtube->video_provider);
        $this->assertSame('dQw4w9WgXcQ', $youtube->vimeo_id);

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Video->value,
            'title' => 'Vimeo lecture',
            'video_url' => 'https://vimeo.com/123456789',
        ])->assertRedirect();

        $vimeo = ContentItem::query()->where('title', 'Vimeo lecture')->firstOrFail();
        $this->assertSame(VideoProvider::Vimeo, $vimeo->video_provider);
        $this->assertSame('123456789', $vimeo->vimeo_id);

        $this->actingAs($instructor)
            ->from(route('teach.show', $offering))
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Video->value,
                'title' => 'Playlist',
                'video_url' => 'https://www.youtube.com/playlist?list=PLxxxx',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('video_url');
    }

    #[Test]
    public function student_sees_youtube_and_vimeo_iframes_after_publish(): void
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
            'type' => ContentItemType::Video->value,
            'title' => 'Hidden YT',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $item = ContentItem::query()->where('title', 'Hidden YT')->firstOrFail();

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertDontSee('Hidden YT');

        $this->actingAs($instructor)->post(route('admin.content-items.publish', $item));

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $item]))
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);

        $legacy = $week->items()->create([
            'type' => ContentItemType::Video,
            'title' => 'Legacy Vimeo',
            'order' => 9,
            'vimeo_id' => '555666',
            'published' => true,
        ]);

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $legacy]))
            ->assertOk()
            ->assertSee('player.vimeo.com/video/555666', false);
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: User}
     */
    private function staffedOffering(): array
    {
        $course = Course::query()->create([
            'code' => 'VID1',
            'title' => 'Video',
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
