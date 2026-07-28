<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_user_with_roles(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'email' => 'instructor@example.com',
            'first_name' => 'Ins',
            'last_name' => 'Tructor',
            'password' => 'Password123!',
            'roles' => [RoleType::Instructor->value],
        ])->assertRedirect();

        $created = User::query()->where('email', 'instructor@example.com')->first();
        $this->assertTrue($created->hasRole(RoleType::Instructor));
    }

    #[Test]
    public function admin_cannot_assign_super_admin_role(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'email' => 'bad@example.com',
            'first_name' => 'Bad',
            'last_name' => 'Actor',
            'password' => 'Password123!',
            'roles' => [RoleType::SuperAdmin->value],
        ])->assertSessionHasErrors('role');
    }

    #[Test]
    public function student_cannot_access_user_admin(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('admin.users.index'))->assertForbidden();
    }
}
