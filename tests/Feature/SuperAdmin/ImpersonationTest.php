<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_impersonate_a_student_then_stop(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Mina',
            'last_name' => 'Guirguis',
        ]);

        $this->actingAs($sa)
            ->post(route('superadmin.people.impersonate', $student), ['confirm' => '1'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($student);
        $this->assertSame($sa->id, session('impersonator_id'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('people.impersonating_title', ['name' => $student->displayName()]))
            ->assertSee(__('people.impersonation_stop'))
            ->assertDontSee(__('dashboard.superadmin_hub'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'users.impersonate.start',
            'actor_id' => $sa->id,
            'entity_id' => $student->id,
        ]);

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('superadmin.index'));

        $this->assertAuthenticatedAs($sa);
        $this->assertNull(session('impersonator_id'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'users.impersonate.stop',
            'actor_id' => $sa->id,
            'entity_id' => $student->id,
        ]);
    }

    #[Test]
    public function cannot_impersonate_super_admin_or_start_without_confirm(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $otherSa = User::factory()->withRole(RoleType::SuperAdmin)->create([
            'email' => 'other-sa@example.com',
        ]);

        $this->actingAs($sa)
            ->post(route('superadmin.people.impersonate', $otherSa), ['confirm' => '1'])
            ->assertSessionHasErrors('user');
        $this->assertAuthenticatedAs($sa);

        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->actingAs($sa)
            ->post(route('superadmin.people.impersonate', $student))
            ->assertSessionHasErrors('confirm');
        $this->assertAuthenticatedAs($sa);
    }

    #[Test]
    public function administrative_admin_cannot_impersonate(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($adm)
            ->post(route('superadmin.people.impersonate', $student), ['confirm' => '1'])
            ->assertForbidden();

        $this->assertAuthenticatedAs($adm);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'users.impersonate.start',
        ]);
    }
}
