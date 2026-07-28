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
}
