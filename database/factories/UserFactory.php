<?php

namespace Database\Factories;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'password_hash' => Hash::make('password'),
            'email_verified' => true,
            'preferred_locale' => 'en',
            'status' => UserStatus::Active,
            'is_reviewer' => false,
        ];
    }

    public function withRole(RoleType $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            UserRole::query()->create([
                'user_id' => $user->id,
                'role' => $role,
            ]);
            $user->load('roles');
        });
    }
}
