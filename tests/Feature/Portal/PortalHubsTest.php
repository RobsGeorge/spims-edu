<?php

namespace Tests\Feature\Portal;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalHubsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function superadmin_sees_dashboard_tiles_and_console(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.learning_hub'))
            ->assertSee(__('dashboard.superadmin_hub'))
            ->assertSee('bi-shield-lock-fill', false);

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.title'))
            ->assertSee(__('superadmin.tile_security'))
            ->assertSee(__('superadmin.tile_audit'));

        $this->actingAs($sa)->get(route('hubs.learning'))->assertOk();
        $this->actingAs($sa)->get(route('hubs.academic'))->assertOk();
        $this->actingAs($sa)->get(route('superadmin.audit.index'))->assertOk();
        $this->actingAs($sa)->get(route('superadmin.observability.index'))->assertOk();
    }

    #[Test]
    public function non_superadmin_cannot_open_console(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('superadmin.index'))->assertForbidden();
        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.superadmin_hub'));
    }

    #[Test]
    public function layout_exposes_hub_nav_for_authenticated_user(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('hubs.nav_learning'))
            ->assertSee(__('hubs.nav_superadmin'));
    }

    #[Test]
    public function superadmin_roles_hub_shows_role_guide(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('roles.hub', ['section' => 'help']))
            ->assertOk()
            ->assertSee(__('roles_hub.section_help'))
            ->assertSee(__('roles_hub.help_levels_title'))
            ->assertSee(__('roles_hub.help_matrix_title'))
            ->assertSee(__('roles_hub.help_gaps_title'))
            ->assertSee(__('roles_hub.help_gap_unsuspend'))
            ->assertSee(__('roles_hub.role_INSTRUCTOR'))
            ->assertSee(__('roles_hub.role_STUDENT'))
            ->assertSee('id="helpSection"', false)
            ->assertSee('accordion-collapse collapse show', false);

        $this->actingAs($sa)->withCookie('locale', 'ar')
            ->get(route('roles.hub', ['section' => 'help']))
            ->assertOk()
            ->assertSee(__('roles_hub.section_help', locale: 'ar'))
            ->assertSee(__('roles_hub.help_gaps_title', locale: 'ar'));
    }
}
