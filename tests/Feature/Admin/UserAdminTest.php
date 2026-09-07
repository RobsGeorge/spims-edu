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

    #[Test]
    public function admin_can_update_name_locale_dob_and_is_reviewer(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $target = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'preferred_locale' => 'en',
            'is_reviewer' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee(__('ui.edit_user'))
            ->assertSee(__('ui.is_reviewer'));

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'first_name' => 'Mariam',
            'last_name' => 'Guirguis',
            'phone' => '0123456789',
            'preferred_locale' => 'ar',
            'country_code' => 'EG',
            'date_of_birth' => '1998-04-12',
            'notify_email' => '1',
            'is_reviewer' => '1',
        ])->assertRedirect(route('admin.users.show', $target));

        $target->refresh();
        $this->assertSame('Mariam', $target->first_name);
        $this->assertSame('Guirguis', $target->last_name);
        $this->assertSame('ar', $target->preferred_locale);
        $this->assertSame('EG', $target->country_code);
        $this->assertSame('1998-04-12', $target->date_of_birth?->toDateString());
        $this->assertTrue($target->notify_email);
        $this->assertTrue($target->is_reviewer);
        $this->assertDatabaseHas('audit_logs', ['action' => 'users.update', 'entity_id' => $target->id]);
    }

    #[Test]
    public function admin_can_assign_instructor_role(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $target = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($admin)->post(route('admin.users.roles.assign', $target), [
            'role' => RoleType::Instructor->value,
        ])->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole(RoleType::Instructor));
        $this->assertTrue($target->fresh()->hasRole(RoleType::Student));
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.assign']);
    }

    #[Test]
    public function student_cannot_edit_user(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $target = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('admin.users.show', $target))->assertForbidden();
        $this->actingAs($student)->put(route('admin.users.update', $target), [
            'first_name' => 'Hacked',
            'last_name' => 'User',
            'preferred_locale' => 'en',
        ])->assertForbidden();
    }
}
