<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_reset_password_with_otp(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create([
            'password_hash' => Hash::make('OldPassword1!'),
        ]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('auth.password.reset.form'));

        $otp = session('dev_otp');

        $this->post(route('auth.password.reset'), [
            'code' => $otp,
            'password' => 'NewPassword2!',
            'password_confirmation' => 'NewPassword2!',
        ])->assertRedirect(route('auth.login'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword2!', $user->password_hash));
    }
}
