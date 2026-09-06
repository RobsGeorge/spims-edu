<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(array $overrides = []): User
    {
        return User::factory()->withRole(RoleType::Student)->create(array_merge([
            'password_hash' => Hash::make('Password123!'),
            'status' => UserStatus::Active,
        ], $overrides));
    }

    #[Test]
    public function login_issues_a_bearer_token_and_the_user_payload(): void
    {
        $user = $this->activeUser();

        $response = $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.roles.0', 'STUDENT')
            ->assertJsonMissingPath('data.user.password_hash');

        $this->assertIsString($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'iPhone 15',
        ]);
        $this->assertTrue(
            AuditLog::query()->where('action', 'auth.api_login')->where('actor_id', $user->id)->exists()
        );
    }

    #[Test]
    public function login_mints_a_token_scoped_to_the_users_roles(): void
    {
        $user = $this->activeUser();

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertOk();

        $token = $user->tokens()->latest()->first();
        $this->assertNotNull($token);
        $this->assertSame(['role:STUDENT'], $token->abilities);
    }

    #[Test]
    public function login_never_touches_the_session_guard(): void
    {
        // AuthService::login() (web) calls Auth::login(), which needs a started
        // session. The api group has no session middleware, so issueApiToken()
        // must not go anywhere near it — this is the regression that motivated
        // splitting the two code paths in the first place.
        $user = $this->activeUser();

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertOk();

        $this->assertGuest('web');
    }

    #[Test]
    public function wrong_password_is_rejected_without_revealing_which_field_was_wrong(): void
    {
        $user = $this->activeUser();

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function an_unknown_email_gets_the_same_generic_failure_as_a_wrong_password(): void
    {
        $this->postJson(route('api.v1.login'), [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    #[Test]
    public function a_suspended_user_is_refused_a_token(): void
    {
        $user = $this->activeUser(['status' => UserStatus::Suspended]);

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function a_pending_user_is_refused_a_token(): void
    {
        $user = $this->activeUser(['status' => UserStatus::Pending]);

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertStatus(422);
    }

    #[Test]
    public function missing_credentials_return_a_422_naming_both_fields(): void
    {
        $this->postJson(route('api.v1.login'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function logout_revokes_only_the_token_used_for_this_request(): void
    {
        $user = $this->activeUser();
        $kept = $user->createToken('other-device')->plainTextToken;
        $revoking = $user->createToken('this-device')->plainTextToken;

        $this->withToken($revoking)
            ->postJson(route('api.v1.logout'))
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        // See MeTest::a_revoked_token_no_longer_authenticates() for why this is
        // needed: the sanctum RequestGuard caches its resolved user per instance,
        // and the test client reuses one instance across requests in this method.
        Auth::forgetGuards();

        $this->withToken($kept)->getJson(route('api.v1.me'))->assertOk();
        Auth::forgetGuards();
        $this->withToken($revoking)->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    #[Test]
    public function logout_without_a_token_is_unauthenticated(): void
    {
        $this->postJson(route('api.v1.logout'))->assertUnauthorized();
    }

    #[Test]
    public function a_zero_role_account_can_log_in_but_the_token_grants_no_ability(): void
    {
        // Reachable via UserAdminService::createUser() with an empty roles array
        // (unlike self-registration, which always assigns Student). Locking this in:
        // it must fail closed for any future `tokenCan()` check, not merely happen
        // to be harmless today by accident.
        $user = $this->activeUser();
        $user->roles()->delete();

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertOk();

        $token = $user->tokens()->latest()->first();
        $this->assertSame([], $token->abilities);
        $this->assertFalse($token->can('role:STUDENT'));
        $this->assertFalse($token->can('anything'));
    }

    #[Test]
    public function the_api_middleware_group_carries_no_session_middleware(): void
    {
        // The reason a browser session cookie can never authenticate an /api/v1
        // request via Sanctum's `guard => ['web']` fallback (Guard::__invoke checks
        // the web session guard before falling back to a bearer token) is
        // architectural, not a runtime check: the `api` group never loads a
        // session, so there is nothing for that guard to read. This test exists so
        // that adding EnsureFrontendRequestsAreStateful, StartSession, or moving
        // these routes into a group that has one is a visible, deliberate change
        // rather than a silent one.
        $middleware = app('router')->getMiddlewareGroups()['api'];

        foreach ($middleware as $entry) {
            $this->assertStringNotContainsString('StartSession', $entry);
            $this->assertStringNotContainsString('EnsureFrontendRequestsAreStateful', $entry);
        }
    }

    #[Test]
    public function repeated_failed_attempts_for_the_same_email_are_throttled(): void
    {
        $user = $this->activeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.v1.login'), [
                'email' => $user->email,
                'password' => 'wrong',
            ])->assertStatus(422);
        }

        $this->postJson(route('api.v1.login'), [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED');
    }

    #[Test]
    public function a_token_older_than_sanctum_expiration_is_unauthenticated(): void
    {
        $minutes = (int) config('sanctum.expiration');
        $this->assertGreaterThan(0, $minutes);

        $user = $this->activeUser();
        $plain = $user->createToken('old-phone')->plainTextToken;
        $user->tokens()->latest()->first()->forceFill([
            'created_at' => now()->subMinutes($minutes + 1),
        ])->save();

        Auth::forgetGuards();

        $this->withToken($plain)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }
}
