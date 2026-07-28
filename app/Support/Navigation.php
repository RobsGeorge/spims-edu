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

        if ($user->isSuperAdmin() || $user->hasRole(RoleType::AcademicAdmin)) {
            $links[] = ['label' => __('ui.nav_programs'), 'route' => 'admin.programs.index'];
            $links[] = ['label' => __('ui.nav_courses'), 'route' => 'admin.courses.index'];
            $links[] = ['label' => __('ui.nav_templates'), 'route' => 'admin.assessment-templates.index'];
            $links[] = ['label' => __('ui.nav_offerings'), 'route' => 'admin.offerings.index'];
        }

        if ($user->isSuperAdmin() || $user->hasRole(RoleType::AdministrativeAdmin)) {
            $links[] = ['label' => __('ui.nav_semesters'), 'route' => 'admin.semesters.index'];
        }

        $links[] = ['label' => __('ui.nav_catalog'), 'route' => 'catalog.index'];

        return $links;
    }
}
