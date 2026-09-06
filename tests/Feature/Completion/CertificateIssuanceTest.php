<?php

namespace Tests\Feature\Completion;

use App\Enums\CompletionCriterionKind;
use App\Enums\CredentialType;
use App\Enums\RoleType;
use App\Models\Credential;
use App\Models\User;
use App\Services\Completion\CompletionService;
use App\Services\Completion\OfferingClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CertificateIssuanceTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function close_issues_one_credential_per_completed_student_and_is_idempotent(): void
    {
        $offering = $this->offering('CERT1');
        $admin = $this->admin();
        $pass = User::factory()->withRole(RoleType::Student)->create();
        $fail = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($pass, $offering);
        $this->enroll($fail, $offering);
        $this->setGrade($offering, $pass, 90);
        $this->setGrade($offering, $fail, 40);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);

        $closing = app(OfferingClosingService::class);
        $closing->lockGrading($admin, $offering);
        $closing->announce($admin, $offering);
        $closing->close($admin, $offering);
        $closing->close($admin, $offering);

        $issued = Credential::query()
            ->where('offering_id', $offering->id)
            ->where('type', CredentialType::OfferingCompletion)
            ->whereNull('revoked_at')
            ->get();

        $this->assertCount(1, $issued);
        $this->assertSame($pass->id, $issued->first()->student_id);

        $this->get(route('credentials.verify', $issued->first()->qr_token))
            ->assertOk()
            ->assertSee(__('credentials.valid'));
    }
}
