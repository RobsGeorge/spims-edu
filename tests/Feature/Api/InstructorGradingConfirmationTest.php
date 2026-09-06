<?php

namespace Tests\Feature\Api;

use App\Enums\OfferingStaffRole;
use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorGradingConfirmationTest extends TestCase
{
    use InstructorGradingFixtures;
    use RefreshDatabase;

    #[Test]
    public function lock_without_a_token_is_422(): void
    {
        $bundle = $this->gradingBundle('S8C1');

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['confirmation']]);
    }

    #[Test]
    public function lock_replay_is_422(): void
    {
        $bundle = $this->gradingBundle('S8C2');
        $token = $this->lockToken($bundle['instructor'], $bundle['offering']);

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => $token,
            ])
            ->assertOk();

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => $token,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['confirmation']]);
    }

    #[Test]
    public function announce_results_without_a_token_is_422(): void
    {
        $bundle = $this->gradingBundle('S8C3');
        $attempt = $this->submittedAttempt($bundle['instructor'], $bundle['offering'], $bundle['student']);

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.assessments.announce-results', $attempt['assessment']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['confirmation']]);
    }

    #[Test]
    public function announce_results_replay_is_422(): void
    {
        $bundle = $this->gradingBundle('S8C4');
        $attempt = $this->submittedAttempt($bundle['instructor'], $bundle['offering'], $bundle['student']);
        $token = $this->announceToken($bundle['instructor'], $attempt['assessment']);

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.assessments.announce-results', $attempt['assessment']), [
                'confirmation' => $token,
            ])
            ->assertOk();

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.assessments.announce-results', $attempt['assessment']), [
                'confirmation' => $token,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['confirmation']]);
    }

    #[Test]
    public function ta_lock_is_403_even_with_a_stolen_looking_token(): void
    {
        $bundle = $this->gradingBundle('S8C5');
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $bundle['offering'], OfferingStaffRole::Ta);

        $this->asApi($ta, 'TA')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => bin2hex(random_bytes(16)),
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
