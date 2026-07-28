<?php

namespace Tests\Feature\Api;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function me_returns_profile_and_roles(): void
    {
        $user = User::factory()->withRole(RoleType::AcademicAdmin)->create([
            'preferred_locale' => 'ar',
        ]);

        $this->actingAs($user)->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonPath('email', $user->email)
            ->assertJsonFragment(['ACADEMIC_ADMIN']);
    }
}
