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
