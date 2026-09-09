<?php

namespace App\Http\Controllers\RolesHub;

use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HelpController;
use App\Services\Help\HelpCatalogService;
use App\Services\Rbac\RolePermissionService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolesHubController extends Controller
{
    public function index(Request $request, RolePermissionService $rbac, AuthorizeService $authorize, HelpCatalogService $helpCatalog): View
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

        
        $locale = app()->getLocale();
        $roleGuides = [];
        foreach (HelpController::guideRoles() as $role) {
            $roleGuides[$role->value] = $helpCatalog->articlesForRoleGuide($role, $locale);
        }
        return view('roles-hub.index', [
            'groups' => $groups,
            'matrix' => $rbac->matrix(),
            'roles' => $rbac->editableRoles(),
            'grantLevels' => RolePermissionService::GRANT_LEVELS,
            'section' => $request->query('section', 'templates'),
            'roleGuides' => $roleGuides,
            'guideRoles' => HelpController::guideRoles(),
            'helpLocale' => $locale,
        ]);
    }

    public function updateRole(Request $request, string $role, RolePermissionService $rbac): RedirectResponse
    {
        $roleType = RoleType::from($role);
        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['nullable', 'string', Rule::in(array_merge([''], RolePermissionService::GRANT_LEVELS))],
        ]);

        $rbac->updateRoleMatrix($request->user(), $roleType, $data['permissions'] ?? []);

        return back()->with('status', __('roles_hub.saved', [
            'role' => __('roles_hub.role_'.$roleType->value),
        ]));
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
