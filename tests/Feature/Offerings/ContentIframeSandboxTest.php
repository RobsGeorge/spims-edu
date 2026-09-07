<?php

namespace Tests\Feature\Offerings;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Enums\VideoProvider;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentIframeSandboxTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX = 'sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"';

    #[Test]
    public function published_youtube_student_page_is_sandboxed_on_nocookie_host(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $student] = $this->enrolledOffering();

        $item = $week->items()->create([
            'type' => ContentItemType::Video,
            'title' => 'Sandboxed lecture',
            'order' => 1,
            'vimeo_id' => 'dQw4w9WgXcQ',
            'video_provider' => VideoProvider::YouTube,
            'published' => true,
        ]);

        $html = $this->actingAs($student)
            ->get(route('learn.item', [$offering, $item]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sandbox=', $html);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
        $this->assertStringContainsString(self::SANDBOX, $html);
        $this->assertStringNotContainsString('allow-top-navigation', $html);
    }

    #[Test]
    public function published_drive_reading_iframe_is_sandboxed(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $student] = $this->enrolledOffering();

        $item = $week->items()->create([
            'type' => ContentItemType::Reading,
            'title' => 'Drive article',
            'order' => 1,
            'file_url' => 'https://drive.google.com/file/d/abcDriveId/preview',
            'published' => true,
        ]);

        $html = $this->actingAs($student)
            ->get(route('learn.item', [$offering, $item]))
            ->assertOk()
            ->assertSee('drive.google.com/file/d/abcDriveId/preview', false)
            ->getContent();

        $this->assertStringContainsString('sandbox=', $html);
        $this->assertStringContainsString(self::SANDBOX, $html);
        $this->assertStringNotContainsString('allow-top-navigation', $html);
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: User}
     */
    private function enrolledOffering(): array
    {
        $course = Course::query()->create([
            'code' => 'SBOX1',
            'title' => 'Sandbox',
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
        $student = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        return [$offering, $week, $student];
    }
}
