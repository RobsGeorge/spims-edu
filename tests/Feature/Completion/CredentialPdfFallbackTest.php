<?php

namespace Tests\Feature\Completion;

use App\Enums\RoleType;
use App\Models\User;
use App\Services\Credentials\CredentialService;
use App\Services\Pdf\PdfRenderService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CredentialPdfFallbackTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function successful_dompdf_render_stores_a_pdf(): void
    {
        $offering = $this->offering('PDF1');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $credential = app(CredentialService::class)->issueOfferingCompletion($admin, $student, $offering, 'en');

        $this->assertNotNull($credential->file_url);
        $this->assertTrue(str_ends_with($credential->file_url, '.pdf'));

        $bytes = app(ObjectStorageService::class)->disk()->get($credential->file_url);
        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    #[Test]
    public function a_renderer_failure_falls_back_to_html_without_throwing(): void
    {
        $offering = $this->offering('PDF2');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->mock(PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('renderPdf')->andReturn(null);
        });

        $credential = app(CredentialService::class)->issueOfferingCompletion($admin, $student, $offering, 'en');

        $this->assertTrue(str_ends_with((string) $credential->file_url, '.html'));
        $bytes = app(ObjectStorageService::class)->disk()->get($credential->file_url);
        $this->assertStringContainsString('<html', $bytes);
    }
}
