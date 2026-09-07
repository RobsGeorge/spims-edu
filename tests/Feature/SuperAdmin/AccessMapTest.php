<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessMapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function hub_reviews_census_exclusive_keys_and_rejects_outsiders(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('superadmin.access'))
            ->assertOk()
            ->assertSee(__('access.page_lead'))
            ->assertSee(__('access.page_help'))
            ->assertSee(__('access.danger_title'))
            ->assertSee(__('access.readonly_title'))
            ->assertSee(__('access.census_title'))
            ->assertSee(__('access.leaks_none'))
            ->assertSee(__('access.elevated_none'))
            ->assertSee(__('roles_hub.role_STUDENT'))
            ->assertSee(__('access.superadmin_title'))
            ->assertSee((string) env('SUPERADMIN_EMAIL'), false)
            ->assertSee('features.manage', false)
            ->assertSee('status.platform', false)
            ->assertSee('access.map', false)
            ->assertSee('data-exclusive-key="features.manage"', false)
            ->assertSee('data-role="STUDENT"', false)
            ->assertSee('data-role="SUPER_ADMIN"', false)
            ->assertDontSee('type="password"', false)
            ->assertDontSee((string) env('SUPERADMIN_PASSWORD'), false);

        $this->actingAs($student)->get(route('superadmin.access'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.access'))->assertForbidden();
        $this->actingAs($student)->get(route('superadmin.access.csv'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.access.csv'))->assertForbidden();
    }

    #[Test]
    public function student_extra_and_exclusive_grants_surface_as_elevated_and_leak(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        RolePermission::query()->firstOrCreate(
            ['role' => RoleType::Student, 'permission_key' => 'finance.refunds'],
            ['level' => 'F']
        );
        RolePermission::query()->firstOrCreate(
            ['role' => RoleType::Student, 'permission_key' => 'features.manage'],
            ['level' => 'F']
        );

        $this->actingAs($sa)->get(route('superadmin.access'))
            ->assertOk()
            ->assertSee(__('access.drifted'))
            ->assertSee('data-elevated-key="finance.refunds"', false)
            ->assertSee('data-elevated-role="STUDENT"', false)
            ->assertSee('data-leak-key="features.manage"', false)
            ->assertSee('data-leak-role="STUDENT"', false)
            ->assertDontSee(__('access.leaks_none'))
            ->assertDontSee(__('access.elevated_none'));
    }

    #[Test]
    public function lookup_lists_holders_versus_defaults(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('superadmin.access', ['q' => 'impersonate']))
            ->assertOk()
            ->assertSee('data-lookup-key="users.impersonate"', false)
            ->assertSee(__('access.holders_none'))
            ->assertSee(__('access.exclusive_only'));
    }

    #[Test]
    public function csv_has_bom_lists_grants_and_is_audited(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $response = $this->actingAs($sa)->get(route('superadmin.access.csv'));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('permission_key', $csv);
        $this->assertStringContainsString('STUDENT', $csv);
        $this->assertStringNotContainsString((string) env('SUPERADMIN_PASSWORD'), $csv);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.map.export',
            'actor_id' => $sa->id,
            'entity_type' => 'AccessMap',
        ]);
        $log = AuditLog::query()->where('action', 'access.map.export')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertGreaterThan(0, (int) ($log->after['keys'] ?? 0));
    }

    #[Test]
    public function dashboard_hub_and_related_pages_link_to_access_map(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('access.dashboard_tile'))
            ->assertSee(__('access.dashboard_tile_hint'))
            ->assertSee(route('superadmin.access'), false);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('access.dashboard_tile'));

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.tile_access'))
            ->assertSee(__('superadmin.tile_access_hint'))
            ->assertSee(__('superadmin.roadmap_sa8_done'))
            ->assertSee(route('superadmin.access'), false);

        $this->actingAs($sa)->get(route('roles.hub'))
            ->assertOk()
            ->assertSee(__('access.entrance_from_roles'))
            ->assertSee(route('superadmin.access'), false)
            ->assertSee('id="role-STUDENT"', false);

        $this->actingAs($sa)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('access.entrance_from_people'));

        $this->actingAs($sa)->get(route('superadmin.security'))
            ->assertOk()
            ->assertSee(__('access.entrance_from_security'));

        $this->actingAs($sa)->get(route('superadmin.features'))
            ->assertOk()
            ->assertSee(__('access.entrance_from_features'));
    }
}
