<?php

namespace Tests\Feature\Credentials;

use App\Enums\RoleType;
use App\Models\User;
use App\Services\Credentials\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Completion\CompletionFixtures;
use Tests\TestCase;

class StudentCredentialDownloadTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function transcript_lists_a_download_link_for_each_owned_credential(): void
    {
        $offering = $this->offering('TRN1');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $peer = User::factory()->withRole(RoleType::Student)->create();
        $credentials = app(CredentialService::class);
        $own = $credentials->issueOfferingCompletion($admin, $student, $offering);
        $other = $credentials->issueOfferingCompletion($admin, $peer, $offering);

        $this->actingAs($student)
            ->get(route('transcript.show'))
            ->assertOk()
            ->assertSee($own->serial)
            ->assertSee(__('credentials.download'))
            ->assertSee(route('credentials.download', $own), false)
            ->assertDontSee($other->serial)
            ->assertDontSee(route('credentials.download', $other), false);
    }

    #[Test]
    public function a_student_can_download_their_own_credential(): void
    {
        $offering = $this->offering('TRN2');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $credential = app(CredentialService::class)->issueOfferingCompletion($admin, $student, $offering);

        $response = $this->actingAs($student)
            ->get(route('credentials.download', $credential));

        $response->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString($credential->serial, $disposition);

        $body = (string) $response->getContent();
        $this->assertTrue(
            str_starts_with($body, '%PDF-') || str_contains($body, '<html') || str_contains($body, '<HTML'),
            'Expected a PDF or HTML credential body'
        );
    }

    #[Test]
    public function legacy_credential_certificate_document_includes_historical_watermark(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        // Create a credential the way L8 import does — with source_system set.
        $credential = \App\Models\Credential::query()->create([
            'student_id' => $student->id,
            'type' => \App\Enums\CredentialType::ProgramCertificate,
            'serial' => 'LEGACY-TEST-001',
            'qr_token' => \Illuminate\Support\Str::uuid(),
            'issued_at' => now()->subYears(3),
            'language' => 'en',
            'source_system' => 'POPULI',
            'source_serial' => 'POPULI-2021-001',
        ]);

        // Render the certificate HTML directly — this is what the PDF renderer
        // receives and what must carry the historical watermark.
        $html = app(\App\Services\Credentials\CertificateTemplateService::class)->render($credential);

        $this->assertStringContainsString(
            __('credentials.historical_download_banner', [], 'en'),
            $html,
            'Legacy credential HTML must include the historical-record banner.'
        );
        $this->assertStringContainsString(
            __('credentials.historical_download_notice', [], 'en'),
            $html,
            'Legacy credential HTML must include the reproduction notice.'
        );
    }

    #[Test]
    public function native_credential_certificate_document_does_not_include_historical_watermark(): void
    {
        $offering = $this->offering('TRN4');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $credential = app(CredentialService::class)->issueOfferingCompletion($admin, $student, $offering);

        $html = app(\App\Services\Credentials\CertificateTemplateService::class)->render($credential);

        $this->assertStringNotContainsString(
            __('credentials.historical_download_banner', [], 'en'),
            $html,
            'A native SPIMS credential must not show the historical-record banner.'
        );
    }

    #[Test]
    public function a_student_cannot_download_another_students_credential(): void
    {
        $offering = $this->offering('TRN3');
        $admin = $this->admin();
        $mine = User::factory()->withRole(RoleType::Student)->create();
        $peer = User::factory()->withRole(RoleType::Student)->create();
        $other = app(CredentialService::class)->issueOfferingCompletion($admin, $peer, $offering);

        $this->actingAs($mine)
            ->get(route('credentials.download', $other))
            ->assertForbidden();
    }
}
