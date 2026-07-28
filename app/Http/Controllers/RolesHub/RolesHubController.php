<?php

namespace App\Http\Controllers\RolesHub;

use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolesHubController extends Controller
{
    public function index(Request $request, RolePermissionService $rbac): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('roles-hub.index', [
            'groups' => $rbac->groupedPermissionKeys(),
            'matrix' => $rbac->matrix(),
            'roles' => $rbac->editableRoles(),
            'section' => $request->query('section', 'templates'),
        ]);
    }

    public function updateRole(Request $request, string $role, RolePermissionService $rbac): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $roleType = RoleType::from($role);
        $data = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'string',
        ]);

        $rbac->updateRoleMatrix($request->user(), $roleType, $data['permissions'] ?? []);

        return back()->with('status', __('roles_hub.saved', ['role' => $roleType->value]));
    }
}
