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
            $links[] = ['label' => __('ui.nav_semesters'), 'route' => 'admin.semesters.index'];
            $links[] = ['label' => __('ui.nav_app_forms'), 'route' => 'admin.application-forms.index'];
            $links[] = ['label' => __('ui.nav_applications'), 'route' => 'admin.applications.index'];
        }

        if ($user->isSuperAdmin() || $user->hasRole(RoleType::AcademicAdmin)) {
            $links[] = ['label' => __('ui.nav_programs'), 'route' => 'admin.programs.index'];
            $links[] = ['label' => __('ui.nav_courses'), 'route' => 'admin.courses.index'];
            $links[] = ['label' => __('ui.nav_templates'), 'route' => 'admin.assessment-templates.index'];
            $links[] = ['label' => __('ui.nav_offerings'), 'route' => 'admin.offerings.index'];
        }

        $links[] = ['label' => __('ui.nav_catalog'), 'route' => 'catalog.index'];
        $links[] = ['label' => __('ui.nav_my_applications'), 'route' => 'applications.index'];
        $links[] = ['label' => __('ui.nav_enrollments'), 'route' => 'enrollments.index'];
        $links[] = ['label' => __('ui.nav_finance'), 'route' => 'finance.index'];
        $links[] = ['label' => __('ui.nav_live'), 'route' => 'live.index'];
        $links[] = ['label' => __('ui.nav_notifications'), 'route' => 'notifications.index'];
        $links[] = ['label' => __('ui.nav_transcript'), 'route' => 'transcript.show'];

        if ($user->isSuperAdmin() || $user->hasRole(RoleType::FinancialAdmin)) {
            $links[] = ['label' => __('ui.nav_finance_admin'), 'route' => 'admin.finance.index'];
        }

        if ($user->isSuperAdmin()
            || $user->hasRole(RoleType::AdministrativeAdmin)
            || $user->hasRole(RoleType::AcademicAdmin)) {
            $links[] = ['label' => __('ui.nav_credentials'), 'route' => 'admin.credentials.index'];
        }

        return $links;
    }
}
