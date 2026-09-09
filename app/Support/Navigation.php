<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    /**
     * @return array<int, array{label: string, route: string, permission?: string}>
     */
    public static function linksFor(User $user): array
    {
        $authz = app(AuthorizeService::class);
        $links = [
            ['label' => __('ui.nav_dashboard'), 'route' => 'dashboard'],
        ];

        if ($authz->allows($user, 'users.manage')) {
            $links[] = ['label' => __('ui.nav_users'), 'route' => 'admin.users.index', 'permission' => 'users.manage'];
        }
        if ($authz->allows($user, 'theme.manage')) {
            $links[] = ['label' => __('ui.nav_theme'), 'route' => 'admin.theme.edit', 'permission' => 'theme.manage'];
        }
        if ($authz->allows($user, 'semesters.manage')) {
            $links[] = ['label' => __('ui.nav_semesters'), 'route' => 'admin.semesters.index'];
        }
        if ($authz->allows($user, 'admissions.forms')) {
            $links[] = ['label' => __('ui.nav_app_forms'), 'route' => 'admin.application-forms.index'];
        }
        if ($authz->allows($user, 'admissions.review')) {
            $links[] = ['label' => __('ui.nav_applications'), 'route' => 'admin.applications.index'];
        }

        if ($authz->allows($user, 'programs.manage')) {
            $links[] = ['label' => __('ui.nav_programs'), 'route' => 'admin.programs.index'];
        }
        if ($authz->allows($user, 'courses.manage')) {
            $links[] = ['label' => __('ui.nav_courses'), 'route' => 'admin.courses.index'];
        }
        if ($authz->allows($user, 'assessment_templates.manage')) {
            $links[] = ['label' => __('ui.nav_templates'), 'route' => 'admin.assessment-templates.index'];
        }
        if ($authz->allows($user, 'offerings.manage')) {
            $links[] = ['label' => __('ui.nav_offerings'), 'route' => 'admin.offerings.index'];
        }

        $links[] = ['label' => __('ui.nav_catalog'), 'route' => 'catalog.index'];
        $links[] = ['label' => __('ui.nav_my_applications'), 'route' => 'applications.index'];
        $links[] = ['label' => __('ui.nav_enrollments'), 'route' => 'enrollments.index'];
        $links[] = ['label' => __('ui.nav_finance'), 'route' => 'finance.index'];
        $links[] = ['label' => __('ui.nav_live'), 'route' => 'live.index'];
        $links[] = ['label' => __('ui.nav_notifications'), 'route' => 'notifications.index'];
        $links[] = ['label' => __('ui.nav_transcript'), 'route' => 'transcript.show'];

        if ($authz->allows($user, NavigationHub::HUB_FINANCE)) {
            $links[] = ['label' => __('ui.nav_finance_admin'), 'route' => 'admin.finance.index'];
        }

        if ($authz->allows($user, 'credentials.issue')) {
            $links[] = ['label' => __('ui.nav_credentials'), 'route' => 'admin.credentials.index'];
        }

        return $links;
    }
}
