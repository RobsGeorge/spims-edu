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

class ContentFileViewerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function instructor_can_upload_pdf_and_image_but_not_html(): void
    {
        Storage::fake('local');
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $instructor] = $this->staffedOffering();

        $this->actingAs($instructor)
            ->from(route('teach.show', $offering))
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Reading->value,
                'title' => 'Notes PDF',
                'file' => UploadedFile::fake()->createWithContent('notes.pdf', "%PDF-1.4\n%%EOF"),
            ])
            ->assertRedirect();

        $pdf = ContentItem::query()->where('title', 'Notes PDF')->firstOrFail();
        $this->assertNotEmpty($pdf->file_url);
        $this->assertFalse(str_starts_with((string) $pdf->file_url, 'http'));
        Storage::disk('local')->assertExists($pdf->file_url);

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::File->value,
            'title' => 'Diagram',
            'file' => UploadedFile::fake()->image('diagram.png', 10, 10),
        ])->assertRedirect();

        $this->actingAs($instructor)
            ->from(route('teach.show', $offering))
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::File->value,
                'title' => 'Bad html',
                'file' => UploadedFile::fake()->create('page.html', 10, 'text/html'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');
    }

    #[Test]
    public function drive_urls_embed_and_unknown_https_is_link_only(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $instructor] = $this->staffedOffering();
        $student = $this->enrollStudent($offering);

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Reading->value,
            'title' => 'Drive article',
            'file_url' => 'https://drive.google.com/file/d/abcDriveId/view?usp=sharing',
        ]);
        $drive = ContentItem::query()->where('title', 'Drive article')->firstOrFail();
        $this->assertSame('https://drive.google.com/file/d/abcDriveId/preview', $drive->file_url);
        $this->actingAs($instructor)->post(route('admin.content-items.publish', $drive));

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Reading->value,
            'title' => 'External notes',
            'file_url' => 'https://example.com/notes.pdf',
        ]);
        $external = ContentItem::query()->where('title', 'External notes')->firstOrFail();
        $this->actingAs($instructor)->post(route('admin.content-items.publish', $external));

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $drive]))
            ->assertOk()
            ->assertSee('drive.google.com/file/d/abcDriveId/preview', false);

        $html = $this->actingAs($student)
            ->get(route('learn.item', [$offering, $external]))
            ->assertOk()
            ->assertSee(__('learn.reading_link_only'))
            ->getContent();
        $this->assertStringNotContainsString('<iframe src="https://example.com/notes.pdf"', $html);
    }

    #[Test]
    public function gated_file_route_enforces_enrollment_and_publish(): void
    {
        Storage::fake('local');
        $this->seed(ThemeSeeder::class);
        [$offering, $week, $instructor] = $this->staffedOffering();
        $student = $this->enrollStudent($offering);
        $other = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($instructor)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Reading->value,
            'title' => 'Gated PDF',
            'file' => UploadedFile::fake()->createWithContent('unit.pdf', "%PDF-1.4\n%%EOF"),
        ]);
        $item = ContentItem::query()->where('title', 'Gated PDF')->firstOrFail();

        $this->actingAs($student)
            ->get(route('learn.item.file', $item))
            ->assertNotFound();

        $this->actingAs($instructor)->post(route('admin.content-items.publish', $item));

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $item]))
            ->assertOk()
            ->assertSee(route('learn.item.file', $item), false)
            ->assertDontSee('/storage/');

        $this->actingAs($student)
            ->get(route('learn.item.file', $item))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $inline = (string) $this->actingAs($student)
            ->get(route('learn.item.file', $item))
            ->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline', $inline);
        $this->assertStringContainsString('filename', $inline);
        $this->assertStringNotContainsString('../', $inline);
        $this->assertStringNotContainsString((string) $item->file_url, $inline);

        $attachment = (string) $this->actingAs($student)
            ->get(route('learn.item.file', ['item' => $item, 'download' => 1]))
            ->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment', $attachment);
        $this->assertStringContainsString('filename', $attachment);

        $this->actingAs($other)
            ->get(route('learn.item.file', $item))
            ->assertForbidden();
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: User}
     */
    private function staffedOffering(): array
    {
        $course = Course::query()->create([
            'code' => 'FIL1',
            'title' => 'Files',
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

    private function enrollStudent(CourseOffering $offering): User
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        return $student;
    }
}
