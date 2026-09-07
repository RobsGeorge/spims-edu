<?php

namespace App\Support;

use App\Enums\RoleType;
use App\Models\Notification;
use App\Models\User;
use App\Services\SuperAdmin\FeatureFlagService;
use App\Services\Teach\TeachAccessService;
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

    public static function hasTeach(?User $user): bool
    {
        return app(TeachAccessService::class)->canTeach($user);
    }

    public static function unreadNotificationCount(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Primary sidebar / drawer destinations.
     *
     * @return list<array{label: string, route: string, icon: string, active: bool}>
     */
    public static function primaryNav(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $items = [
            [
                'label' => __('hubs.nav_home'),
                'route' => 'dashboard',
                'icon' => 'bi-house',
                'active' => request()->routeIs('dashboard'),
            ],
        ];

        if (self::featureEnabled('learn')) {
            $items[] = [
                'label' => __('hubs.nav_learning'),
                'route' => 'hubs.learning',
                'icon' => 'bi-book-half',
                'active' => request()->routeIs('hubs.learning')
                    || request()->routeIs('courses.*')
                    || request()->routeIs('grades.*')
                    || request()->routeIs('enrollments.*')
                    || request()->routeIs('attendance.*')
                    || request()->routeIs('events.*')
                    || request()->routeIs('live-quiz.*')
                    || request()->routeIs('student.projects.*')
                    || request()->routeIs('student.surveys.*')
                    || request()->routeIs('learn.*'),
            ];
        }

        if (self::hasTeach($user) && Route::has('teach.index') && self::featureEnabled('teach')) {
            $items[] = [
                'label' => __('hubs.nav_teach'),
                'route' => 'teach.index',
                'icon' => 'bi-easel2',
                'active' => request()->routeIs('teach.*') || request()->routeIs('advising.*'),
            ];
        }

        if (self::hasAcademicAdmin($user)) {
            $items[] = [
                'label' => __('hubs.nav_academic'),
                'route' => 'hubs.academic',
                'icon' => 'bi-mortarboard',
                'active' => request()->routeIs('hubs.academic') || request()->routeIs('admin.programs.*') || request()->routeIs('admin.courses.*') || request()->routeIs('admin.offerings.*') || request()->routeIs('admin.reports.*') || request()->routeIs('advising.*'),
            ];
        }

        if (self::hasAdministrative($user)) {
            $items[] = [
                'label' => __('hubs.nav_admin'),
                'route' => 'hubs.admin',
                'icon' => 'bi-gear',
                'active' => request()->routeIs('hubs.admin') || request()->routeIs('admin.users.*') || request()->routeIs('admin.applications.*') || request()->routeIs('admin.enrollments.*'),
            ];
        }

        $items[] = [
            'label' => __('hubs.nav_finance'),
            'route' => 'hubs.finance',
            'icon' => 'bi-wallet2',
            'active' => request()->routeIs('hubs.finance') || request()->routeIs('finance.*') || request()->routeIs('admin.finance.*'),
        ];

        if (self::hasSuperadmin($user)) {
            $items[] = [
                'label' => __('hubs.nav_superadmin'),
                'route' => 'superadmin.index',
                'icon' => 'bi-shield-lock',
                'active' => request()->routeIs('superadmin.*') || request()->routeIs('roles.hub'),
                'tone' => 'superadmin',
            ];
        }

        return array_values(array_filter($items, fn (array $item): bool => Route::has($item['route'])));
    }

    /**
     * Mobile bottom-nav (max 5).
     *
     * @return list<array{label: string, route: string, icon: string, active: bool}>
     */
    public static function bottomNav(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $third = self::hasTeach($user) && Route::has('teach.index') && self::featureEnabled('teach')
            ? ['label' => __('hubs.nav_teach'), 'route' => 'teach.index', 'icon' => 'bi-easel2', 'active' => request()->routeIs('teach.*')]
            : (self::featureEnabled('public_catalog')
                ? ['label' => __('hubs.catalog'), 'route' => 'catalog.index', 'icon' => 'bi-journal-bookmark', 'active' => request()->routeIs('catalog.*')]
                : ['label' => __('hubs.nav_more'), 'route' => 'settings.edit', 'icon' => 'bi-grid', 'active' => request()->routeIs('settings.*')]);

        $fifth = self::hasSuperadmin($user) && Route::has('superadmin.index')
            ? [
                'label' => __('hubs.nav_superadmin'),
                'route' => 'superadmin.index',
                'icon' => 'bi-shield-lock',
                'active' => request()->routeIs('superadmin.*') || request()->routeIs('roles.hub'),
                'tone' => 'superadmin',
            ]
            : ['label' => __('hubs.nav_more'), 'route' => 'settings.edit', 'icon' => 'bi-grid', 'active' => request()->routeIs('settings.*') || request()->routeIs('notifications.*')];

        $items = [
            ['label' => __('hubs.nav_home'), 'route' => 'dashboard', 'icon' => 'bi-house', 'active' => request()->routeIs('dashboard')],
        ];
        if (self::featureEnabled('learn')) {
            $items[] = ['label' => __('hubs.nav_learning'), 'route' => 'hubs.learning', 'icon' => 'bi-book-half', 'active' => request()->routeIs('hubs.learning') || request()->routeIs('courses.*') || request()->routeIs('events.*') || request()->routeIs('live-quiz.*') || request()->routeIs('student.projects.*') || request()->routeIs('student.surveys.*')];
        }
        $items[] = $third;
        $items[] = ['label' => __('hubs.nav_finance'), 'route' => 'hubs.finance', 'icon' => 'bi-wallet2', 'active' => request()->routeIs('hubs.finance') || request()->routeIs('finance.*')];
        $items[] = $fifth;

        return array_values(array_filter($items, fn (array $item): bool => Route::has($item['route'])));
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string, description?: string}>
     */
    public static function learningLinks(User $user): array
    {
        return array_values(array_filter([
            self::link('catalog.index', 'hubs.catalog', 'bi-journal-bookmark', 'hubs.catalog_desc', 'public_catalog'),
            self::link('grades.index', 'hubs.grades', 'bi-clipboard-data', 'hubs.grades_desc', 'learn'),
            self::link('applications.index', 'hubs.my_applications', 'bi-file-earmark-text', 'hubs.my_applications_desc', 'admissions'),
            self::link('enrollments.index', 'hubs.enrollments', 'bi-person-check', 'hubs.enrollments_desc'),
            self::link('student.projects.mine', 'hubs.projects', 'bi-people', 'hubs.projects_desc', 'projects'),
            self::link('live.index', 'hubs.live', 'bi-camera-video', 'hubs.live_desc', 'live'),
            self::link('live-quiz.join', 'hubs.live_quiz', 'bi-lightning-charge', 'hubs.live_quiz_desc', 'live_quiz'),
            self::link('events.index', 'events.hub', 'bi-calendar-event', 'events.hub_desc', 'events'),
            self::link('attendance.index', 'hubs.attendance', 'bi-calendar-check', 'hubs.attendance_desc'),
            self::link('student.surveys.index', 'hubs.surveys', 'bi-clipboard-check', 'hubs.surveys_desc', 'surveys'),
            self::link('finance.index', 'hubs.finance', 'bi-wallet2', 'hubs.finance_desc'),
            self::link('transcript.show', 'hubs.transcript', 'bi-award', 'hubs.transcript_desc'),
            self::link('settings.edit', 'hubs.settings', 'bi-person-gear', 'hubs.settings_desc'),
            self::link('notifications.index', 'hubs.notifications', 'bi-bell', 'hubs.notifications_desc'),
            self::link('announcements.index', 'hubs.announcements', 'bi-megaphone', 'hubs.announcements_desc'),
            self::link('settings.notifications.edit', 'hubs.notification_settings', 'bi-sliders', 'hubs.notification_settings_desc'),
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
            self::link('advising.index', 'hubs.advising', 'bi-person-lines-fill', 'hubs.advising_desc'),
            self::link('admin.programs.index', 'hubs.programs', 'bi-mortarboard', 'hubs.programs_desc'),
            self::link('admin.courses.index', 'hubs.courses', 'bi-book', 'hubs.courses_desc'),
            self::link('admin.offerings.index', 'hubs.offerings', 'bi-calendar3', 'hubs.offerings_desc'),
            self::link('admin.assessment-templates.index', 'hubs.templates', 'bi-ui-checks-grid', 'hubs.templates_desc'),
            self::link('admin.semesters.index', 'hubs.semesters', 'bi-calendar-range', 'hubs.semesters_desc'),
            self::link('admin.credentials.index', 'hubs.credentials', 'bi-patch-check', 'hubs.credentials_desc'),
            self::link('admin.grading-schemes.index', 'hubs.grading_schemes', 'bi-bar-chart-steps', 'hubs.grading_schemes_desc'),
            self::link('admin.translations.index', 'hubs.translations', 'bi-translate', 'hubs.translations_desc'),
            self::link('admin.attendance.policy', 'hubs.attendance_admin', 'bi-clipboard-check', 'hubs.attendance_admin_desc'),
            self::link('admin.communications.report', 'hubs.communications', 'bi-envelope-paper', 'hubs.communications_desc'),
            self::link('admin.email-templates.index', 'hubs.email_templates', 'bi-file-earmark-text', 'hubs.email_templates_desc'),
            self::link('admin.certificate-templates.index', 'hubs.certificate_templates', 'bi-award', 'hubs.certificate_templates_desc'),
            self::link('admin.surveys.index', 'staff.surveys.hub', 'bi-clipboard-data', 'staff.surveys.hub_desc', 'surveys'),
            self::link('admin.reports.index', 'hubs.reports', 'bi-file-earmark-bar-graph', 'hubs.reports_desc'),
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
            self::link('advising.index', 'hubs.advising', 'bi-person-lines-fill', 'hubs.advising_desc'),
            self::link('admin.enrollments.index', 'hubs.enrollment_admin', 'bi-person-plus', 'hubs.enrollment_admin_desc'),
            self::link('admin.theme.edit', 'hubs.theme', 'bi-palette', 'hubs.theme_desc'),
            self::link('admin.application-forms.index', 'hubs.app_forms', 'bi-ui-checks', 'hubs.app_forms_desc'),
            self::link('admin.applications.index', 'hubs.applications', 'bi-inbox', 'hubs.applications_desc'),
            self::link('admin.communications.report', 'hubs.communications', 'bi-envelope-paper', 'hubs.communications_desc'),
            self::link('admin.events.index', 'staff.events.hub', 'bi-calendar-event', 'staff.events.hub_desc', 'events'),
            self::link('admin.reports.index', 'hubs.reports', 'bi-file-earmark-bar-graph', 'hubs.reports_desc'),
        ]));
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string, description?: string}>
     */
    public static function financeLinks(User $user): array
    {
        $links = [
            self::link('finance.index', 'hubs.finance', 'bi-wallet2', 'hubs.finance_desc'),
            self::link('donate.create', 'hubs.donate', 'bi-heart', 'hubs.donate_desc', 'finance_checkout'),
        ];

        if (self::hasFinanceAdmin($user)) {
            $links[] = self::link('admin.finance.index', 'hubs.finance_admin', 'bi-cash-stack', 'hubs.finance_admin_desc');
            $links[] = self::link('admin.finance.reports', 'hubs.finance_reports', 'bi-graph-up', 'hubs.finance_reports_desc');
            $links[] = self::link('admin.reports.index', 'hubs.reports', 'bi-file-earmark-bar-graph', 'hubs.reports_desc');
        }

        return array_values(array_filter($links));
    }

    /**
     * Superadmin control-plane sections. Tiles whose route is missing are omitted.
     *
     * @return list<array{id: string, title: string, description: string, links: list<array<string, mixed>>}>
     */
    public static function superadminSections(): array
    {
        $sections = [
            [
                'id' => 'people',
                'title' => __('superadmin.section_people'),
                'description' => __('superadmin.section_people_desc'),
                'links' => array_values(array_filter([
                    self::superadminTile('admin.users.index', 'superadmin.tile_users', 'bi-people', 'superadmin.tile_users_desc', 'superadmin.tile_users_hint'),
                ])),
            ],
            [
                'id' => 'access',
                'title' => __('superadmin.section_access'),
                'description' => __('superadmin.section_access_desc'),
                'links' => array_values(array_filter([
                    self::superadminTile('roles.hub', 'superadmin.tile_roles', 'bi-shield-check', 'superadmin.tile_roles_desc', 'superadmin.tile_roles_hint'),
                    self::superadminTile('superadmin.security', 'superadmin.tile_security', 'bi-shield-lock', 'superadmin.tile_security_desc', 'superadmin.tile_security_hint'),
                ])),
            ],
            [
                'id' => 'appearance',
                'title' => __('superadmin.section_appearance'),
                'description' => __('superadmin.section_appearance_desc'),
                'links' => array_values(array_filter([
                    self::superadminTile('superadmin.theme.index', 'superadmin.tile_theme', 'bi-palette', 'superadmin.tile_theme_desc', 'superadmin.tile_theme_hint'),
                    self::superadminTile('admin.theme.edit', 'superadmin.tile_theme_staff', 'bi-brush', 'superadmin.tile_theme_staff_desc', 'superadmin.tile_theme_staff_hint'),
                ])),
            ],
            [
                'id' => 'platform',
                'title' => __('superadmin.section_platform'),
                'description' => __('superadmin.section_platform_desc'),
                'links' => array_values(array_filter([
                    self::superadminTile('superadmin.features', 'superadmin.tile_features', 'bi-toggles', 'superadmin.tile_features_desc', 'superadmin.tile_features_hint'),
                    self::superadminTile('superadmin.config', 'superadmin.tile_config', 'bi-sliders2', 'superadmin.tile_config_desc', 'superadmin.tile_config_hint'),
                ])),
            ],
            [
                'id' => 'school',
                'title' => __('superadmin.section_school'),
                'description' => __('superadmin.section_school_desc'),
                'links' => array_values(array_filter([
                    self::superadminTile('hubs.academic', 'superadmin.tile_academics', 'bi-journal-bookmark-fill', 'superadmin.tile_academics_desc', 'superadmin.tile_academics_hint'),
                    self::superadminTile('admin.finance.index', 'superadmin.tile_finance', 'bi-credit-card', 'superadmin.tile_finance_desc', 'superadmin.tile_finance_hint'),
                    self::superadminTile('admin.credentials.index', 'superadmin.tile_credentials', 'bi-patch-check-fill', 'superadmin.tile_credentials_desc', 'superadmin.tile_credentials_hint'),
                    self::superadminTile('admin.application-forms.index', 'superadmin.tile_admissions', 'bi-ui-checks', 'superadmin.tile_admissions_desc', 'superadmin.tile_admissions_hint'),
                    self::superadminTile('admin.applications.index', 'superadmin.tile_applications', 'bi-inbox', 'superadmin.tile_applications_desc', 'superadmin.tile_applications_hint'),
                    self::superadminTile('admin.enrollments.index', 'superadmin.tile_enrollment', 'bi-person-plus', 'superadmin.tile_enrollment_desc', 'superadmin.tile_enrollment_hint'),
                    self::superadminTile('admin.finance.reports', 'superadmin.tile_finance_reports', 'bi-graph-up', 'superadmin.tile_finance_reports_desc', 'superadmin.tile_finance_reports_hint'),
                ])),
            ],
            [
                'id' => 'evidence',
                'title' => __('superadmin.section_evidence'),
                'description' => __('superadmin.section_evidence_desc'),
                'links' => array_values(array_filter([
                    self::superadminTile('superadmin.reports', 'superadmin.tile_reports', 'bi-bar-chart-line', 'superadmin.tile_reports_desc', 'superadmin.tile_reports_hint'),
                    self::superadminTile('superadmin.audit.index', 'superadmin.tile_audit', 'bi-journal-text', 'superadmin.tile_audit_desc', 'superadmin.tile_audit_hint'),
                    self::superadminTile('superadmin.observability.index', 'superadmin.tile_observability', 'bi-activity', 'superadmin.tile_observability_desc', 'superadmin.tile_observability_hint'),
                    self::superadminTile('health', 'superadmin.tile_health', 'bi-heart-pulse', 'superadmin.tile_health_desc', 'superadmin.tile_health_hint'),
                ])),
            ],
            [
                'id' => 'ops',
                'title' => __('superadmin.section_ops'),
                'description' => __('superadmin.section_ops_desc'),
                'links' => array_values(array_filter([
                    self::superadminTile('superadmin.scheduled-tasks.index', 'superadmin.tile_scheduled', 'bi-clock-history', 'superadmin.tile_scheduled_desc', 'superadmin.tile_scheduled_hint'),
                    self::superadminTile('superadmin.system-tests.index', 'superadmin.tile_system_tests', 'bi-clipboard2-check', 'superadmin.tile_system_tests_desc', 'superadmin.tile_system_tests_hint'),
                    self::superadminTile('superadmin.feedback-reveals.index', 'superadmin.tile_reveals', 'bi-eye-slash', 'superadmin.tile_reveals_desc', 'superadmin.tile_reveals_hint'),
                ])),
            ],
        ];

        return array_values(array_filter(
            $sections,
            fn (array $section): bool => $section['links'] !== []
        ));
    }

    /**
     * @return array{label: string, url: string, icon: string, description: string, hint: string, superadmin_only: bool, route: string}|null
     */
    private static function superadminTile(string $route, string $labelKey, string $icon, string $descKey, ?string $hintKey = null): ?array
    {
        if (! Route::has($route)) {
            return null;
        }

        return [
            'label' => __($labelKey),
            'url' => route($route),
            'icon' => $icon,
            'description' => __($descKey),
            'hint' => $hintKey ? __($hintKey) : '',
            'superadmin_only' => true,
            'route' => $route,
        ];
    }

    private static function featureEnabled(string $flag): bool
    {
        return app(FeatureFlagService::class)->enabled($flag);
    }

    /**
     * @return array{label: string, route: string, icon: string, description: string}|null
     */
    private static function link(string $route, string $labelKey, string $icon, string $descKey, ?string $feature = null): ?array
    {
        if ($feature !== null && ! self::featureEnabled($feature)) {
            return null;
        }

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
