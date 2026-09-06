<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_authenticated_users_profile_and_roles(): void
    {
        $user = User::factory()->withRole(RoleType::Instructor)->create([
            'preferred_locale' => 'fr',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.preferred_locale', 'fr')
            ->assertJsonPath('data.roles.0', 'INSTRUCTOR')
            ->assertJsonMissingPath('data.password_hash');
    }

    #[Test]
    public function a_user_with_multiple_roles_gets_every_role_listed(): void
    {
        $user = User::factory()->withRole(RoleType::Instructor)->create();
        $user->roles()->create(['role' => RoleType::AcademicAdmin]);

        $token = $user->createToken('test')->plainTextToken;

        $roles = $this->withToken($token)->getJson(route('api.v1.me'))->json('data.roles');

        $this->assertEqualsCanonicalizing(['INSTRUCTOR', 'ACADEMIC_ADMIN'], $roles);
    }

    #[Test]
    public function it_401s_without_a_token(): void
    {
        $this->getJson(route('api.v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function it_401s_with_a_garbage_token(): void
    {
        $this->withToken('not-a-real-token')
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    #[Test]
    public function a_revoked_token_no_longer_authenticates(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create();
        $token = $user->createToken('test');

        $this->withToken($token->plainTextToken)->getJson(route('api.v1.me'))->assertOk();

        $token->accessToken->delete();

        // Sanctum's `sanctum` guard is a RequestGuard, which caches the resolved
        // user for the lifetime of the guard instance (see
        // Illuminate\Auth\RequestGuard::user()). The test HTTP client reuses one
        // application container across requests in a single test method, so
        // without forgetting the cached guard, this second call would still see
        // the guard's answer from before the token was deleted — a testing
        // artifact, not something that can happen across real, separate requests
        // in production.
        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)->getJson(route('api.v1.me'))->assertUnauthorized();
    }
}
