<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Rbac\RolePermissionService;
use App\Support\AuthorizeService;
use App\Support\NavigationHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ControlPlaneSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function student_cannot_open_superadmin_or_roles_hub(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('superadmin.index'))->assertForbidden();
        $this->actingAs($student)->get(route('roles.hub'))->assertForbidden();
        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.superadmin_hub'))
            ->assertDontSee(__('superadmin.entrance_title'), false)
            ->assertDontSee(__('people.dashboard_tile'))
            ->assertDontSee(__('audit.dashboard_tile'))
            ->assertDontSee(__('features.dashboard_tile'))
            ->assertDontSee(__('system_settings.dashboard_tile'))
            ->assertDontSee(__('theme_studio.dashboard_tile'))
            ->assertDontSee(__('school_reports.dashboard_tile'))
            ->assertDontSee(__('ops.dashboard_tile'))
            ->assertDontSee(__('status.dashboard_tile'))
            ->assertDontSee(__('access.dashboard_tile'));
    }

    #[Test]
    public function administrative_admin_cannot_open_roles_hub_or_console(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($adm)->get(route('superadmin.index'))->assertForbidden();
        $this->actingAs($adm)->get(route('roles.hub'))->assertForbidden();
        $this->actingAs($adm)->post(route('roles.hub.role.reset', RoleType::Student->value))
            ->assertForbidden();
    }

    #[Test]
    public function nobody_can_assign_super_admin_role(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $authz = app(AuthorizeService::class);

        $this->assertFalse($authz->canAssignRole($sa, RoleType::SuperAdmin));
        $this->assertFalse($authz->canAssignRole($adm, RoleType::SuperAdmin));
        $this->assertTrue($authz->canAssignRole($sa, RoleType::Instructor));
        $this->assertFalse($authz->canAssignRole($adm, RoleType::AdministrativeAdmin));

        foreach ([$sa, $adm] as $actor) {
            $this->actingAs($actor)->post(route('admin.users.store'), [
                'email' => 'minted-sa-'.uniqid().'@example.com',
                'first_name' => 'Minted',
                'last_name' => 'Admin',
                'password' => 'Password123!',
                'roles' => [RoleType::SuperAdmin->value],
            ])->assertSessionHasErrors('role');
        }
    }

    #[Test]
    public function user_create_form_omits_super_admin_checkbox(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('superadmin.users_roles_help'))
            ->assertSee(__('people.directory_title'))
            ->assertSee(__('people.search_label'))
            ->assertDontSee('name="roles[]" value="'.RoleType::SuperAdmin->value.'"', false);
    }

    #[Test]
    public function control_plane_hub_is_sectioned_and_linked_from_existing_pages(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.section_people'))
            ->assertSee(__('superadmin.section_access'))
            ->assertSee(__('superadmin.section_appearance'))
            ->assertSee(__('superadmin.section_platform'))
            ->assertSee(__('superadmin.section_school'))
            ->assertSee(__('superadmin.section_evidence'))
            ->assertSee(__('superadmin.section_ops'))
            ->assertSee(__('superadmin.bypass_title'))
            ->assertSee(__('superadmin.tile_roles'))
            ->assertSee(__('superadmin.tile_features'))
            ->assertSee(__('superadmin.tile_config'))
            ->assertSee(__('superadmin.tile_theme'))
            ->assertSee(__('superadmin.tile_reports'))
            ->assertSee(__('superadmin.tile_ops'))
            ->assertSee(__('superadmin.tile_status'))
            ->assertSee(__('superadmin.tile_access'))
            ->assertSee(route('roles.hub'), false)
            ->assertSee(route('superadmin.features'), false)
            ->assertSee(route('superadmin.config'), false)
            ->assertSee(route('superadmin.theme.index'), false)
            ->assertSee(route('superadmin.reports'), false)
            ->assertSee(route('superadmin.ops'), false)
            ->assertSee(route('superadmin.status'), false)
            ->assertSee(route('superadmin.access'), false)
            ->assertSee(__('superadmin.roadmap_title'));

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.superadmin_hub'))
            ->assertSee(route('superadmin.index'), false)
            ->assertSee(__('hubs.nav_superadmin'))
            ->assertSee(__('people.dashboard_tile'))
            ->assertSee(route('admin.users.index'), false)
            ->assertSee(__('audit.dashboard_tile'))
            ->assertSee(route('superadmin.audit.index'), false)
            ->assertSee(__('features.dashboard_tile'))
            ->assertSee(route('superadmin.features'), false)
            ->assertSee(__('system_settings.dashboard_tile'))
            ->assertSee(route('superadmin.config'), false)
            ->assertSee(__('theme_studio.dashboard_tile'))
            ->assertSee(route('superadmin.theme.index'), false)
            ->assertSee(__('school_reports.dashboard_tile'))
            ->assertSee(route('superadmin.reports'), false)
            ->assertSee(__('ops.dashboard_tile'))
            ->assertSee(route('superadmin.ops'), false)
            ->assertSee(__('status.dashboard_tile'))
            ->assertSee(route('superadmin.status'), false)
            ->assertSee(__('access.dashboard_tile'))
            ->assertSee(route('superadmin.access'), false);

        $this->actingAs($sa)->get(route('hubs.admin'))
            ->assertOk()
            ->assertSee(__('superadmin.entrance_title'));

        $this->actingAs($sa)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(__('superadmin.entrance_from_settings'))
            ->assertSee(__('features.entrance_from_settings'))
            ->assertSee(__('system_settings.entrance_from_settings'));
    }

    #[Test]
    public function roles_hub_lists_control_plane_keys_and_can_reset_a_role(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $rbac = app(RolePermissionService::class);

        $this->assertContains('features.manage', $rbac->permissionKeys());
        $this->assertContains('users.impersonate', $rbac->permissionKeys());

        $this->actingAs($sa)->get(route('roles.hub'))
            ->assertOk()
            ->assertSee(__('roles_hub.search_label'))
            ->assertSee(__('roles_hub.reset_role'))
            ->assertSee(__('roles_hub.group_features'))
            ->assertSee(__('roles_hub.group_status'))
            ->assertSee(__('roles_hub.group_access'))
            ->assertSee('features.manage')
            ->assertSee('users.impersonate')
            ->assertSee('status.platform')
            ->assertSee('access.map')
            ->assertDontSee(__('roles_hub.role_SUPER_ADMIN'));

        $rbac->updateRoleMatrix($sa, RoleType::Student, ['transcript.view']);
        $this->assertFalse(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.view')
                ->exists()
        );

        $this->actingAs($sa)->post(route('roles.hub.role.reset', RoleType::Student->value))
            ->assertRedirect();

        $this->assertTrue(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.view')
                ->exists()
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rbac.role_matrix.reset',
            'actor_id' => $sa->id,
        ]);
    }

    #[Test]
    public function upcoming_control_plane_tiles_are_hidden_until_routed(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('superadmin.features'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('superadmin.config'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('superadmin.theme.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('superadmin.reports'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('superadmin.ops'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('superadmin.status'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('superadmin.access'));
        $sections = NavigationHub::superadminSections();
        $urls = collect($sections)->pluck('links')->flatten(1)->pluck('url');
        $this->assertTrue($urls->contains(fn ($url) => str_contains((string) $url, '/superadmin/features')));
        $this->assertTrue($urls->contains(fn ($url) => str_contains((string) $url, '/superadmin/config')));
        $this->assertTrue($urls->contains(fn ($url) => str_contains((string) $url, '/superadmin/theme')));
        $this->assertTrue($urls->contains(fn ($url) => str_contains((string) $url, '/superadmin/reports')));
        $this->assertTrue($urls->contains(fn ($url) => str_contains((string) $url, '/superadmin/ops')));
        $this->assertTrue($urls->contains(fn ($url) => str_contains((string) $url, '/superadmin/status')));
        $this->assertTrue($urls->contains(fn ($url) => str_contains((string) $url, '/superadmin/access')));
    }
}
