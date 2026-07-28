<?php

namespace App\Support;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class NavigationHub
{
    public static function hasSuperadmin(?User $user): bool
    {
        return $user !== null && $user->isSuperAdmin();
    }

    public static function hasAcademicAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->hasRole(RoleType::AcademicAdmin);
    }

    public static function hasAdministrative(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->hasRole(RoleType::AdministrativeAdmin);
    }

    public static function hasFinanceAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->hasRole(RoleType::FinancialAdmin);
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string, description?: string}>
     */
    public static function learningLinks(User $user): array
    {
        return array_values(array_filter([
            self::link('catalog.index', 'hubs.catalog', 'bi-journal-bookmark', 'hubs.catalog_desc'),
            self::link('applications.index', 'hubs.my_applications', 'bi-file-earmark-text', 'hubs.my_applications_desc'),
            self::link('enrollments.index', 'hubs.enrollments', 'bi-person-check', 'hubs.enrollments_desc'),
            self::link('live.index', 'hubs.live', 'bi-camera-video', 'hubs.live_desc'),
            self::link('finance.index', 'hubs.finance', 'bi-wallet2', 'hubs.finance_desc'),
            self::link('transcript.show', 'hubs.transcript', 'bi-award', 'hubs.transcript_desc'),
            self::link('notifications.index', 'hubs.notifications', 'bi-bell', 'hubs.notifications_desc'),
        ]));
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string, description?: string}>
     */
    public static function academicLinks(User $user): array
    {
        if (! self::hasAcademicAdmin($user) && ! $user->isSuperAdmin()) {
            return [];
        }

        return array_values(array_filter([
            self::link('admin.programs.index', 'hubs.programs', 'bi-mortarboard', 'hubs.programs_desc'),
            self::link('admin.courses.index', 'hubs.courses', 'bi-book', 'hubs.courses_desc'),
            self::link('admin.offerings.index', 'hubs.offerings', 'bi-calendar3', 'hubs.offerings_desc'),
            self::link('admin.assessment-templates.index', 'hubs.templates', 'bi-ui-checks-grid', 'hubs.templates_desc'),
            self::link('admin.semesters.index', 'hubs.semesters', 'bi-calendar-range', 'hubs.semesters_desc'),
            self::link('admin.credentials.index', 'hubs.credentials', 'bi-patch-check', 'hubs.credentials_desc'),
        ]));
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string, description?: string}>
     */
    public static function adminLinks(User $user): array
    {
        if (! self::hasAdministrative($user) && ! $user->isSuperAdmin()) {
            return [];
        }

        return array_values(array_filter([
            self::link('admin.users.index', 'hubs.users', 'bi-people', 'hubs.users_desc'),
            self::link('admin.theme.edit', 'hubs.theme', 'bi-palette', 'hubs.theme_desc'),
            self::link('admin.application-forms.index', 'hubs.app_forms', 'bi-ui-checks', 'hubs.app_forms_desc'),
            self::link('admin.applications.index', 'hubs.applications', 'bi-inbox', 'hubs.applications_desc'),
        ]));
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string, description?: string}>
     */
    public static function financeLinks(User $user): array
    {
        $links = [
            self::link('finance.index', 'hubs.finance', 'bi-wallet2', 'hubs.finance_desc'),
            self::link('donate.create', 'hubs.donate', 'bi-heart', 'hubs.donate_desc'),
        ];

        if (self::hasFinanceAdmin($user)) {
            $links[] = self::link('admin.finance.index', 'hubs.finance_admin', 'bi-cash-stack', 'hubs.finance_admin_desc');
        }

        return array_values(array_filter($links));
    }

    /**
     * Superadmin exclusive console tiles (Deaconia-shaped, SPIMS-mapped).
     *
     * @return list<array{title: string, links: list<array{label: string, url: string, icon: string, description: string, superadmin_only: bool}>}>
     */
    public static function superadminSections(): array
    {
        $links = [
            [
                'label' => __('superadmin.tile_users'),
                'url' => route('admin.users.index'),
                'icon' => 'bi-people',
                'description' => __('superadmin.tile_users_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_academics'),
                'url' => route('hubs.academic'),
                'icon' => 'bi-journal-bookmark-fill',
                'description' => __('superadmin.tile_academics_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_roles'),
                'url' => route('admin.users.index'),
                'icon' => 'bi-shield-check',
                'description' => __('superadmin.tile_roles_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_security'),
                'url' => route('superadmin.security'),
                'icon' => 'bi-shield-lock',
                'description' => __('superadmin.tile_security_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_audit'),
                'url' => route('superadmin.audit.index'),
                'icon' => 'bi-journal-text',
                'description' => __('superadmin.tile_audit_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_observability'),
                'url' => route('superadmin.observability.index'),
                'icon' => 'bi-activity',
                'description' => __('superadmin.tile_observability_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_system_tests'),
                'url' => route('superadmin.system-tests.index'),
                'icon' => 'bi-clipboard2-check',
                'description' => __('superadmin.tile_system_tests_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_scheduled'),
                'url' => route('superadmin.scheduled-tasks.index'),
                'icon' => 'bi-clock-history',
                'description' => __('superadmin.tile_scheduled_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_theme'),
                'url' => route('admin.theme.edit'),
                'icon' => 'bi-palette',
                'description' => __('superadmin.tile_theme_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_finance'),
                'url' => route('admin.finance.index'),
                'icon' => 'bi-credit-card',
                'description' => __('superadmin.tile_finance_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_credentials'),
                'url' => route('admin.credentials.index'),
                'icon' => 'bi-patch-check-fill',
                'description' => __('superadmin.tile_credentials_desc'),
                'superadmin_only' => true,
            ],
            [
                'label' => __('superadmin.tile_health'),
                'url' => route('health'),
                'icon' => 'bi-heart-pulse',
                'description' => __('superadmin.tile_health_desc'),
                'superadmin_only' => true,
            ],
        ];

        return [
            [
                'title' => __('superadmin.section_exclusive'),
                'links' => $links,
            ],
        ];
    }

    /**
     * @return array{label: string, route: string, icon: string, description: string}|null
     */
    private static function link(string $route, string $labelKey, string $icon, string $descKey): ?array
    {
        if (! Route::has($route)) {
            return null;
        }

        return [
            'label' => __($labelKey),
            'route' => $route,
            'icon' => $icon,
            'description' => __($descKey),
        ];
    }
}
