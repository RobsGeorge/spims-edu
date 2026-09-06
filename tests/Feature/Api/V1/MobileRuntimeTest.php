<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Http\Kernel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Documents how /api/v1 is served: same Laravel app, stateless Sanctum, CORS on api/*.
 */
class MobileRuntimeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_v1_is_mounted_on_the_same_app_as_health(): void
    {
        $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function cors_covers_the_mobile_prefix_and_the_api_group_is_stateless(): void
    {
        $this->assertContains('api/*', config('cors.paths'));
        $this->assertFalse(config('cors.supports_credentials'));

        $apiGroup = app(Kernel::class)->getMiddlewareGroups()['api'];
        $this->assertNotContains(
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            $apiGroup
        );
    }

    #[Test]
    public function bearer_token_from_login_authenticates_me(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create([
            'password_hash' => Hash::make('Password123!'),
            'status' => UserStatus::Active,
        ]);

        $token = $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'pipeline-smoke',
        ])->assertOk()->json('data.token');

        $this->getJson(route('api.v1.me'), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()->assertJsonPath('data.email', $user->email);
    }

    #[Test]
    public function sanctum_expiration_reads_minutes_from_env(): void
    {
        $this->assertNull(config('sanctum.expiration'));

        config(['sanctum.expiration' => (int) '43200']);
        $this->assertSame(43200, config('sanctum.expiration'));
    }
}
