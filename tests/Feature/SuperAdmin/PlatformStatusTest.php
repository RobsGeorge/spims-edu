<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function hub_embeds_health_integrations_runtime_and_rejects_outsiders(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $html = $this->actingAs($sa)->get(route('superadmin.status'))
            ->assertOk()
            ->assertSee(__('status.page_lead'))
            ->assertSee(__('status.page_help'))
            ->assertSee(__('status.danger_title'))
            ->assertSee(__('status.readonly_title'))
            ->assertSee(__('status.health_title'))
            ->assertSee(__('status.health_ok'))
            ->assertSee(__('status.health_pass'))
            ->assertSee(__('status.check_app'))
            ->assertSee(__('status.check_database'))
            ->assertSee(__('status.check_cache'))
            ->assertSee(__('system_settings.integration_paypal'))
            ->assertSee(__('status.configured'))
            ->assertSee(__('status.demo_missing'))
            ->assertSee((string) config('queue.default'), false)
            ->assertSee((string) config('session.driver'), false)
            ->assertSee((string) config('cache.default'), false)
            ->assertSee((string) config('mail.default'), false)
            ->assertSee('data-health-status="ok"', false)
            ->assertSee('data-health-check="app"', false)
            ->assertSee('data-health-check="database"', false)
            ->assertSee('data-health-check="cache"', false)
            ->assertSee('data-integration="paypal"', false)
            ->assertDontSee('type="password"', false)
            ->assertDontSee('name="MAIL_PASSWORD"', false)
            ->assertDontSee('name="PAYPAL_SECRET"', false)
            ->assertDontSee((string) env('SUPERADMIN_PASSWORD'), false)
            ->getContent();

        $this->assertStringNotContainsString('PGPASSWORD', $html);
        $this->assertStringNotContainsString('value="'.(string) env('SUPERADMIN_PASSWORD').'"', $html);

        $this->actingAs($student)->get(route('superadmin.status'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.status'))->assertForbidden();
    }

    #[Test]
    public function dashboard_hub_and_related_pages_link_to_status_desk(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('status.dashboard_tile'))
            ->assertSee(__('status.dashboard_tile_hint'))
            ->assertSee(route('superadmin.status'), false);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('status.dashboard_tile'));

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.tile_status'))
            ->assertSee(__('superadmin.tile_status_hint'))
            ->assertSee(__('superadmin.roadmap_sa7_done'))
            ->assertSee(route('superadmin.status'), false);

        $this->actingAs($sa)->get(route('superadmin.observability.index'))
            ->assertOk()
            ->assertSee(__('status.entrance_from_observability'))
            ->assertSee(route('superadmin.status'), false)
            ->assertSee('failed_jobs', false);

        $this->actingAs($sa)->get(route('superadmin.config'))
            ->assertOk()
            ->assertSee(__('status.entrance_from_config'))
            ->assertSee(route('superadmin.status'), false);

        $this->actingAs($sa)->get(route('superadmin.ops'))
            ->assertOk()
            ->assertSee(__('status.entrance_from_ops'))
            ->assertSee(route('superadmin.status'), false);

        $this->actingAs($sa)->get(route('superadmin.security'))
            ->assertOk()
            ->assertSee(__('status.entrance_from_security'));

        $this->actingAs($sa)->get(route('superadmin.system-tests.index'))
            ->assertOk()
            ->assertSee(__('status.entrance_from_tests'));

        $this->actingAs($sa)->get(route('hubs.admin'))
            ->assertOk()
            ->assertSee(__('status.entrance_from_admin'));

        $this->actingAs($sa)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(__('status.entrance_from_settings'));
    }

    #[Test]
    public function public_health_json_still_reports_ok(): void
    {
        $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.app', true)
            ->assertJsonPath('checks.database', true);
    }
}
