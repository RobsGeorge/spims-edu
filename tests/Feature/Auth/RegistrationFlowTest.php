<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_register_verify_set_password_and_login(): void
    {
        $this->post(route('auth.register'), [
            'email' => 'student@example.com',
            'first_name' => 'Test',
            'last_name' => 'Student',
        ])->assertRedirect(route('auth.verify'));

        $otp = session('dev_otp');
        $this->assertNotEmpty($otp);

        $this->post(route('auth.verify'), ['code' => $otp])->assertRedirect(route('auth.password.create'));

        $this->post(route('auth.password.create'), [
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'student@example.com')->first();
        $this->assertTrue($user->hasRole(RoleType::Student));
        $this->assertSame(UserStatus::Active, $user->status);
    }
}
