<?php

namespace Tests\Feature\Rbac;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
            ->assertSee('programs.manage')
            ->assertSee(__('roles_hub.level_off'))
            ->assertSee(__('roles_hub.level_R'))
            ->assertSee(__('roles_hub.section_help'))
            ->assertSee(__('roles_hub.help_jump'))
            ->assertSee('name="permissions[programs.manage]"', false)
            ->assertDontSee('name="permissions[]"', false);

        $help = $this->actingAs($sa)->get(route('roles.hub', ['section' => 'help']))
            ->assertOk()
            ->getContent();

        foreach ([
            __('roles_hub.help_intro_title'),
            __('roles_hub.help_vs_title'),
            __('roles_hub.help_gap_holds'),
            __('roles_hub.help_nav_body'),
        ] as $needle) {
            $this->assertTrue(str_contains($help, $needle), 'Missing from role guide: '.$needle);
        }

        $this->actingAs($sa)->put(route('roles.hub.role.update', RoleType::Student->value), [
            'permissions' => [
                'programs.view' => 'R',
                'transcript.view' => 'O',
            ],
        ])->assertRedirect();

        $this->assertSame(
            'R',
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.view')
                ->value('level')
        );
        $this->assertFalse(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'catalog.index')
                ->exists()
        );
    }

    #[Test]
    public function granting_a_key_student_never_had_stores_explicit_read_not_full(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->assertFalse(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.manage')
                ->exists()
        );

        $this->actingAs($sa)->put(route('roles.hub.role.update', RoleType::Student->value), [
            'permissions' => [
                'programs.manage' => 'R',
            ],
        ])->assertRedirect();

        $level = RolePermission::query()
            ->where('role', RoleType::Student->value)
            ->where('permission_key', 'programs.manage')
            ->value('level');

        $this->assertSame('R', $level);
        $this->assertNotSame('F', $level);

        $audit = AuditLog::query()
            ->where('action', 'rbac.role_matrix.update')
            ->where('actor_id', $sa->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('R', $audit->after['levels']['programs.manage'] ?? null);
        $this->assertArrayNotHasKey('programs.manage', $audit->before['levels'] ?? []);
    }

    #[Test]
    public function empty_level_deletes_the_row(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->assertTrue(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.view')
                ->exists()
        );

        $this->actingAs($sa)->put(route('roles.hub.role.update', RoleType::Student->value), [
            'permissions' => [
                'programs.view' => '',
                'transcript.view' => 'O',
            ],
        ])->assertRedirect();

        $this->assertFalse(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.view')
                ->exists()
        );
        $this->assertSame(
            'O',
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'transcript.view')
                ->value('level')
        );
    }

    #[Test]
    public function reset_restores_shipped_levels_including_lock_reopen_issue(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $rbac = app(RolePermissionService::class);

        $rbac->updateRoleMatrix($sa, RoleType::Instructor, ['roster.view' => 'R']);
        $rbac->updateRoleMatrix($sa, RoleType::AcademicAdmin, ['programs.view' => 'R']);
        $rbac->updateRoleMatrix($sa, RoleType::AdministrativeAdmin, ['users.manage' => 'F']);

        $this->actingAs($sa)->post(route('roles.hub.role.reset', RoleType::Instructor->value))
            ->assertRedirect();
        $this->actingAs($sa)->post(route('roles.hub.role.reset', RoleType::AcademicAdmin->value))
            ->assertRedirect();
        $this->actingAs($sa)->post(route('roles.hub.role.reset', RoleType::AdministrativeAdmin->value))
            ->assertRedirect();

        $this->assertSame(
            'lock',
            RolePermission::query()
                ->where('role', RoleType::Instructor->value)
                ->where('permission_key', 'gradebook.lock')
                ->value('level')
        );
        $this->assertSame(
            'reopen',
            RolePermission::query()
                ->where('role', RoleType::AcademicAdmin->value)
                ->where('permission_key', 'gradebook.reopen')
                ->value('level')
        );
        $this->assertSame(
            'issue',
            RolePermission::query()
                ->where('role', RoleType::AdministrativeAdmin->value)
                ->where('permission_key', 'credentials.issue')
                ->value('level')
        );
    }

    #[Test]
    public function super_admin_only_key_requires_explicit_level(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->put(route('roles.hub.role.update', RoleType::Student->value), [
            'permissions' => [
                'features.manage' => 'R',
            ],
        ])->assertRedirect();

        $this->assertSame(
            'R',
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'features.manage')
                ->value('level')
        );
    }

    #[Test]
    public function unknown_key_and_unknown_level_are_rejected(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->put(route('roles.hub.role.update', RoleType::Student->value), [
            'permissions' => [
                'not.a.real.key' => 'R',
            ],
        ])->assertSessionHasErrors('permissions.not.a.real.key');

        $this->actingAs($sa)->put(route('roles.hub.role.update', RoleType::Student->value), [
            'permissions' => [
                'programs.view' => 'Z',
            ],
        ])->assertSessionHasErrors('permissions.programs.view');

        $this->actingAs($sa)->put(route('roles.hub.role.update', RoleType::Student->value), [
            'permissions' => ['programs.manage'],
        ])->assertSessionHasErrors();

        $this->assertFalse(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.manage')
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
            ['transcript.view' => 'O']
        );

        $student = User::factory()->withRole(RoleType::Student)->create();
        $authz = app(\App\Support\AuthorizeService::class);
        $authz->forgetMatrixCache();

        $authz->authorize($student, 'transcript.view');

        $this->expectException(\App\Exceptions\AuthorizationException::class);
        $authz->authorize($student, 'programs.view');
    }

    #[Test]
    public function service_rejects_list_payload_instead_of_defaulting_to_full(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        try {
            app(RolePermissionService::class)->updateRoleMatrix(
                $sa,
                RoleType::Student,
                ['programs.manage']
            );
            $this->fail('List payload must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permissions', $e->errors());
        }

        $this->assertFalse(
            RolePermission::query()
                ->where('role', RoleType::Student->value)
                ->where('permission_key', 'programs.manage')
                ->exists()
        );
    }
}
