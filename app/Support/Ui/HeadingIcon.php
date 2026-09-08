<?php

namespace App\Support\Ui;

/**
 * Maps the current route name to an <x-icon> vocabulary key so page titles
 * pick up a relevant mark without editing every Blade file.
 */
final class HeadingIcon
{
    /**
     * Exact route → vocabulary key.
     *
     * @var array<string, string>
     */
    private const EXACT = [
        'dashboard' => 'home',
        'home' => 'home',
        'catalog.index' => 'catalog',
        'hubs.learning' => 'learning',
        'hubs.academic' => 'academic',
        'hubs.admin' => 'admin',
        'hubs.finance' => 'finance',
        'roles.hub' => 'superadmin',
        'auth.login' => 'login',
        'auth.register' => 'register',
        'transcript.show' => 'transcript',
        'credentials.verify' => 'credential',
    ];

    /**
     * Longest-prefix-first route prefixes.
     *
     * @var array<string, string>
     */
    private const PREFIXES = [
        'admin.certificate-templates' => 'credential',
        'admin.application-forms' => 'application',
        'admin.communications' => 'announcement',
        'admin.grading-schemes' => 'grade',
        'admin.academic-years' => 'calendar',
        'admin.translations' => 'translation',
        'admin.credentials' => 'credential',
        'admin.assessments' => 'assessment',
        'admin.applications' => 'application',
        'admin.enrollments' => 'enrollment',
        'admin.attendance' => 'attendance',
        'admin.completion' => 'completion',
        'admin.offering-closing' => 'completion',
        'admin.gradebook' => 'grade',
        'admin.programs' => 'program',
        'admin.courses' => 'course',
        'admin.offerings' => 'offering',
        'admin.semesters' => 'calendar',
        'admin.reports' => 'report',
        'admin.finance' => 'finance',
        'admin.users' => 'people',
        'admin.theme' => 'theme',
        'admin.events' => 'event',
        'admin.live' => 'live',
        'staff.surveys' => 'survey',
        'student.surveys' => 'survey',
        'student.projects' => 'project',
        'live-quiz' => 'quiz',
        'announcements' => 'announcement',
        'notifications' => 'notification',
        'discussions' => 'discussion',
        'enrollments' => 'enrollment',
        'applications' => 'application',
        'attendance' => 'attendance',
        'completion' => 'completion',
        'credentials' => 'credential',
        'assignments' => 'assessment',
        'assessments' => 'assessment',
        'superadmin' => 'superadmin',
        'advising' => 'advising',
        'settings' => 'settings',
        'projects' => 'project',
        'surveys' => 'survey',
        'catalog' => 'catalog',
        'finance' => 'finance',
        'grades' => 'grade',
        'events' => 'event',
        'learn' => 'learning',
        'courses' => 'course',
        'teach' => 'teach',
        'live' => 'live',
        'auth' => 'lock',
    ];

    public static function for(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        if (isset(self::EXACT[$routeName])) {
            return self::EXACT[$routeName];
        }

        foreach (self::PREFIXES as $prefix => $icon) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix.'.')) {
                return $icon;
            }
        }

        return null;
    }
}
