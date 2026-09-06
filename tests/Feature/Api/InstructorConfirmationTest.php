<?php

namespace Tests\Feature\Api;

use App\Enums\OfferingClosingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorConfirmationTest extends TestCase
{
    use InstructorApiFixtures;
    use RefreshDatabase;

    #[Test]
    public function close_without_a_token_is_422_validation_failed(): void
    {
        $world = $this->staffTwoOfferings();

        $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->postJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/close')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.confirmation.0', __('api.confirmation_required'));
    }

    #[Test]
    public function a_valid_close_token_is_consumed_once_and_replayed_tokens_are_422(): void
    {
        $world = $this->staffTwoOfferings();
        $this->announceReady($world['admin'], $world['offeringA'], $world['studentA']);

        $token = $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->getJson('/api/v1/teach/offerings/'.$world['offeringA']->id)
            ->assertOk()
            ->json('data.confirmation')['offering.close']['confirmation_token'];

        $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->postJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/close', [
                'confirmation' => $token,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OfferingClosingStatus::Closed->value);

        $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->postJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/close', [
                'confirmation' => $token,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.confirmation.0', __('api.confirmation_invalid'));
    }
}
