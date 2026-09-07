<?php

namespace App\Http\Controllers\RolesHub;

use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Services\Rbac\RolePermissionService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolesHubController extends Controller
{
    public function index(Request $request, RolePermissionService $rbac, AuthorizeService $authorize): View
    {
        $authorize->authorize($request->user(), 'roles.manage_matrix');

        $groups = [];
        foreach ($rbac->groupedPermissionKeys() as $group => $keys) {
            $groups[] = [
                'id' => $group,
                'label' => $rbac->groupLabel($group),
                'keys' => $keys,
            ];
        }

        return view('roles-hub.index', [
            'groups' => $groups,
            'matrix' => $rbac->matrix(),
            'roles' => $rbac->editableRoles(),
            'section' => $request->query('section', 'templates'),
        ]);
    }

    public function updateRole(Request $request, string $role, RolePermissionService $rbac): RedirectResponse
    {
        $roleType = RoleType::from($role);
        $data = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'string',
        ]);

        $rbac->updateRoleMatrix($request->user(), $roleType, $data['permissions'] ?? []);

        return back()->with('status', __('roles_hub.saved', ['role' => $roleType->value]));
    }

    public function resetRole(Request $request, string $role, RolePermissionService $rbac): RedirectResponse
    {
        $roleType = RoleType::from($role);
        $written = $rbac->resetRoleFromConfig($request->user(), $roleType);

        return back()->with('status', __('roles_hub.reset_saved', [
            'role' => __('roles_hub.role_'.$roleType->value),
            'count' => $written,
        ]));
    }
}
