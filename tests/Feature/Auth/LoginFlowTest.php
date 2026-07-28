<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function active_user_can_login_and_logout(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create([
            'password_hash' => Hash::make('Secret123!'),
        ]);

        $this->post(route('auth.login'), [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('auth.logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    #[Test]
    public function invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create([
            'password_hash' => Hash::make('Secret123!'),
        ]);

        $this->post(route('auth.login'), [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
    }
}
