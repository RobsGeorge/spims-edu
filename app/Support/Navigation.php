<?php

namespace App\Support;

use App\Enums\RoleType;
use App\Models\User;

class Navigation
{
    /**
     * @return array<int, array{label: string, route: string, permission?: string}>
     */
    public static function linksFor(User $user): array
    {
        $links = [
            ['label' => __('ui.nav_dashboard'), 'route' => 'dashboard'],
        ];

        if ($user->isSuperAdmin() || $user->hasRole(RoleType::AdministrativeAdmin)) {
            $links[] = ['label' => __('ui.nav_users'), 'route' => 'admin.users.index', 'permission' => 'users.manage'];
            $links[] = ['label' => __('ui.nav_theme'), 'route' => 'admin.theme.edit', 'permission' => 'theme.manage'];
        }

        return $links;
    }
}
