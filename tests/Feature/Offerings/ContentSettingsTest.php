<?php

namespace Tests\Feature\Offerings;

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
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function youtube_can_be_disabled_by_superadmin_config(): void
    {
        $this->seed(ThemeSeeder::class);
        config(['spims.content.video_providers' => ['VIMEO']]);
        [, $week, $instructor] = $this->staffed();

        $this->actingAs($instructor)
            ->from(route('teach.show', $week->offering))
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Video->value,
                'title' => 'No YT',
                'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('video_url');

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Video->value,
            'title' => 'Vimeo ok',
            'video_url' => '123456789',
        ])->assertRedirect();
        $this->assertNotNull(ContentItem::query()->where('title', 'Vimeo ok')->first());
    }

    #[Test]
    public function unknown_reading_hosts_can_be_closed(): void
    {
        $this->seed(ThemeSeeder::class);
        config(['spims.content.allow_unknown_reading_urls' => false]);
        [, $week, $instructor] = $this->staffed();

        $this->actingAs($instructor)
            ->from(route('teach.show', $week->offering))
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Reading->value,
                'title' => 'Random',
                'file_url' => 'https://example.com/a.pdf',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('file_url');
    }

    #[Test]
    public function download_flag_hides_student_download(): void
    {
        Storage::fake('local');
        $this->seed(ThemeSeeder::class);
        config(['spims.content.student_file_download' => false]);
        [$offering, $week, $instructor] = $this->staffed();
        $student = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Reading->value,
            'title' => 'Locked PDF',
            'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF"),
        ]);
        $item = ContentItem::query()->where('title', 'Locked PDF')->firstOrFail();
        $this->actingAs($instructor)->post(route('admin.content-items.publish', $item));

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $item]))
            ->assertOk()
            ->assertDontSee(__('learn.file_download'));

        $this->actingAs($student)
            ->get(route('learn.item.file', ['item' => $item, 'download' => 1]))
            ->assertForbidden();
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: User}
     */
    private function staffed(): array
    {
        $course = Course::query()->create(['code' => 'CFG1', 'title' => 'Cfg', 'credit_hours' => 1, 'active' => true]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);
        $week = Week::query()->create(['offering_id' => $offering->id, 'number' => 1, 'title' => 'W', 'order' => 1]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $week, $instructor];
    }
}
