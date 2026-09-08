<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\OtpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeopleDirectoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function directory_filters_by_email_name_role_and_status(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $mary = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Mary',
            'last_name' => 'Nisreen',
            'email' => 'mary.nisreen@example.com',
            'preferred_locale' => 'ar',
            'status' => UserStatus::Active,
        ]);
        $bob = User::factory()->withRole(RoleType::Instructor)->create([
            'first_name' => 'Bob',
            'last_name' => 'Hanna',
            'email' => 'bob.hanna@example.com',
            'preferred_locale' => 'en',
            'status' => UserStatus::Suspended,
        ]);

        $this->actingAs($sa)->get(route('admin.users.index', ['q' => 'mary.nisreen']))
            ->assertOk()
            ->assertSee('mary.nisreen@example.com')
            ->assertDontSee('bob.hanna@example.com')
            ->assertSee(route('admin.users.show', $mary), false)
            ->assertSee(__('people.search_help'));

        $this->actingAs($sa)->get(route('admin.users.index', ['q' => 'Hanna']))
            ->assertOk()
            ->assertSee('bob.hanna@example.com')
            ->assertDontSee('mary.nisreen@example.com');

        $this->actingAs($sa)->get(route('admin.users.index', ['role' => RoleType::Instructor->value]))
            ->assertOk()
            ->assertSee('bob.hanna@example.com')
            ->assertDontSee('mary.nisreen@example.com');

        $this->actingAs($sa)->get(route('admin.users.index', ['status' => UserStatus::Suspended->value]))
            ->assertOk()
            ->assertSee('bob.hanna@example.com')
            ->assertDontSee('mary.nisreen@example.com');

        $this->actingAs($sa)->get(route('admin.users.index', ['locale' => 'ar']))
            ->assertOk()
            ->assertSee('mary.nisreen@example.com')
            ->assertDontSee('bob.hanna@example.com');

        $this->actingAs($sa)->get(route('admin.users.show', $mary))
            ->assertOk()
            ->assertSee(__('people.dossier_title'))
            ->assertSee(__('people.impersonate'))
            ->assertSee($mary->email);
    }

    #[Test]
    public function super_admin_can_unsuspend_and_the_write_is_audited(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'status' => UserStatus::Suspended,
        ]);

        $this->actingAs($sa)->post(route('admin.users.unsuspend', $student))->assertRedirect();

        $this->assertSame(UserStatus::Active, $student->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'users.unsuspend',
            'actor_id' => $sa->id,
            'entity_id' => $student->id,
        ]);
    }

    #[Test]
    public function revoking_the_last_role_assigns_student(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();

        $this->actingAs($sa)
            ->delete(route('admin.users.roles.destroy', [$instructor, RoleType::Instructor->value]))
            ->assertRedirect();

        $instructor->refresh()->load('roles');
        $this->assertFalse($instructor->hasRole(RoleType::Instructor));
        $this->assertTrue($instructor->hasRole(RoleType::Student));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'roles.revoke',
            'actor_id' => $sa->id,
            'entity_id' => $instructor->id,
        ]);
    }

    #[Test]
    public function cannot_suspend_self_or_seeded_super_admin(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($sa)->post(route('admin.users.suspend', $sa))
            ->assertSessionHasErrors('user');
        $this->assertSame(UserStatus::Active, $sa->fresh()->status);

        $this->actingAs($adm)->post(route('admin.users.suspend', $sa))
            ->assertSessionHasErrors('user');
        $this->assertSame(UserStatus::Active, $sa->fresh()->status);

        $this->actingAs($adm)->post(route('admin.users.suspend', $adm))
            ->assertSessionHasErrors('user');
        $this->assertSame(UserStatus::Active, $adm->fresh()->status);
    }

    #[Test]
    public function administrative_admin_can_unsuspend_and_a_student_cannot(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'status' => UserStatus::Suspended,
        ]);
        $other = User::factory()->withRole(RoleType::Student)->create([
            'status' => UserStatus::Suspended,
        ]);

        $this->actingAs($adm)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('people.directory_title'))
            ->assertDontSee(__('people.impersonate'));

        $this->actingAs($adm)->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee($student->email)
            ->assertSee(__('people.unsuspend'))
            ->assertDontSee(__('people.impersonate_start'));

        $this->actingAs($adm)->post(route('admin.users.unsuspend', $student))
            ->assertRedirect();
        $this->assertSame(UserStatus::Active, $student->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'users.unsuspend',
            'actor_id' => $adm->id,
            'entity_id' => $student->id,
        ]);

        $this->actingAs($other)->post(route('admin.users.unsuspend', $other))
            ->assertForbidden();
        $this->assertSame(UserStatus::Suspended, $other->fresh()->status);
    }

    #[Test]
    public function force_activate_and_password_reset_are_audited(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $pending = User::factory()->create([
            'status' => UserStatus::Pending,
            'email_verified' => false,
        ]);

        $this->actingAs($sa)->post(route('admin.users.activate', $pending))->assertRedirect();
        $pending->refresh()->load('roles');
        $this->assertSame(UserStatus::Active, $pending->status);
        $this->assertTrue($pending->email_verified);
        $this->assertTrue($pending->hasRole(RoleType::Student));

        $this->actingAs($sa)->post(route('admin.users.password-reset', $pending))
            ->assertRedirect()
            ->assertSessionHas('dev_otp');

        $this->assertTrue(
            OtpToken::query()
                ->where('user_id', $pending->id)
                ->where('purpose', \App\Enums\OtpPurpose::PasswordReset)
                ->whereNull('consumed_at')
                ->exists()
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'users.password_reset_issued',
            'actor_id' => $sa->id,
            'entity_id' => $pending->id,
        ]);
    }

    #[Test]
    public function student_cannot_open_directory_or_dossier(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $other = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.users.show', $other))->assertForbidden();
    }
}
