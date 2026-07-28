<?php

namespace Tests\Feature\Rbac;

use App\Enums\RoleType;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolesHubTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function superadmin_can_view_and_update_role_matrix(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('roles.hub'))
            ->assertOk()
            ->assertSee(__('roles_hub.title'))
            ->assertSee('programs.manage');

        $this->actingAs($sa)->put(route('roles.hub.role.update', RoleType::Student->value), [
            'permissions' => ['programs.view', 'transcript.view'],
        ])->assertRedirect();

        $this->assertTrue(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.view')
                ->exists()
        );
        $this->assertFalse(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'catalog.index')
                ->exists()
        );
    }

    #[Test]
    public function non_superadmin_cannot_open_roles_hub(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($adm)->get(route('roles.hub'))->assertForbidden();
    }

    #[Test]
    public function authorize_service_respects_db_matrix(): void
    {
        $this->seed();
        app(RolePermissionService::class)->updateRoleMatrix(
            User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail(),
            RoleType::Student,
            ['transcript.view']
        );

        $student = User::factory()->withRole(RoleType::Student)->create();
        $authz = app(\App\Support\AuthorizeService::class);
        $authz->forgetMatrixCache();

        $authz->authorize($student, 'transcript.view');

        $this->expectException(\App\Exceptions\AuthorizationException::class);
        $authz->authorize($student, 'programs.view');
    }
}
