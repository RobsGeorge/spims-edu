<?php

namespace Tests\Feature\Offerings;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentFileHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PDF = "%PDF-1.4\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear($this->catalogPreviewFileLimiterKey());
        RateLimiter::clear('catalog-preview-file');
    }

    #[Test]
    public function html_bytes_named_pdf_are_rejected(): void
    {
        Storage::fake('local');
        [, $week, $instructor] = $this->staffedOffering();

        $this->actingAs($instructor)
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Reading->value,
                'title' => 'Spoofed notes',
                'file' => UploadedFile::fake()->createWithContent(
                    'notes.pdf',
                    '<html><body>not a pdf</body></html>',
                ),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, ContentItem::query()->count());
    }

    #[Test]
    public function tiny_pdf_magic_is_accepted(): void
    {
        Storage::fake('local');
        [, $week, $instructor] = $this->staffedOffering();

        $this->actingAs($instructor)
            ->post(route('admin.weeks.items', $week), [
                'type' => ContentItemType::Reading->value,
                'title' => 'Tiny PDF',
                'file' => UploadedFile::fake()->createWithContent('notes.pdf', self::TINY_PDF),
            ])
            ->assertRedirect();

        $item = ContentItem::query()->where('title', 'Tiny PDF')->firstOrFail();
        $this->assertNotEmpty($item->file_url);
        Storage::disk('local')->assertExists($item->file_url);
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get($item->file_url));
    }

    #[Test]
    public function public_preview_disposition_is_encoded_without_traversal(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/week/foo../notes.pdf', self::TINY_PDF);

        $offering = $this->guestOffering();
        $week = $offering->weeks->firstWhere('number', 1);
        $item = $week->items()->create([
            'type' => ContentItemType::Reading,
            'title' => '../Week 1 ملف',
            'order' => 1,
            'file_url' => 'uploads/week/foo../notes.pdf',
            'published' => true,
        ]);

        $response = $this->get(route('offerings.preview.item.file', [$offering, $item]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline', $disposition);
        $this->assertStringContainsString('filename', $disposition);
        $this->assertTrue(
            str_contains($disposition, 'filename*') || str_contains($disposition, 'utf-8'),
            'Content-Disposition should RFC 5987-encode a non-ASCII title: '.$disposition,
        );
        $this->assertStringNotContainsString('../', $disposition);
        $this->assertStringNotContainsString('foo../notes.pdf', $disposition);
        $this->assertStringNotContainsString((string) $item->file_url, $disposition);

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);
    }

    #[Test]
    public function public_preview_file_is_rate_limited_after_thirty_hits(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/week/catalog.pdf', self::TINY_PDF);

        $offering = $this->guestOffering();
        $week = $offering->weeks->firstWhere('number', 1);
        $item = $week->items()->create([
            'type' => ContentItemType::Reading,
            'title' => 'Syllabus',
            'order' => 1,
            'file_url' => 'uploads/week/catalog.pdf',
            'published' => true,
        ]);

        $key = $this->catalogPreviewFileLimiterKey();
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::increment($key, 60);
        }

        $this->get(route('offerings.preview.item.file', [$offering, $item]))
            ->assertStatus(429);
    }

    /**
     * @return array{0: CourseOffering, 1: Week, 2: User}
     */
    private function staffedOffering(): array
    {
        $offering = $this->guestOffering();
        $week = $offering->weeks->firstWhere('number', 1);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $week, $instructor];
    }

    private function guestOffering(): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'HRD1',
            'title' => 'Hardening',
            'credit_hours' => 1,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);
        Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);

        return $offering->fresh(['weeks.items', 'course']);
    }

    private function catalogPreviewFileLimiterKey(): string
    {
        $ip = request()->ip() ?: '127.0.0.1';

        return md5('catalog-preview-file'.$ip);
    }
}
