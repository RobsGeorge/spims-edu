<?php

namespace App\Support;

use App\Enums\OfferingStatus;
use App\Models\CourseOffering;

/**
 * Public trial personas. Emails are @spims.test only — never the super admin.
 */
final class DemoPersonas
{
    /**
     * @return list<array{
     *     slug: string,
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     featured: bool,
     *     icon: string,
     *     landing: string,
     *     locale: string,
     *     roles: list<string>
     * }>
     */
    public static function all(): array
    {
        return [
            self::persona('student1', 'student1@spims.test', 'John', 'Student', true, 'bi-mortarboard', 'learn.th101', 'en', ['STUDENT']),
            self::persona('student6', 'student6@spims.test', 'Hannah', 'Rizk', true, 'bi-translate', 'dashboard', 'ar', ['STUDENT']),
            self::persona('ins1', 'ins1@spims.test', 'Mina', 'Instructor', true, 'bi-easel', 'teach.th101', 'en', ['INSTRUCTOR']),
            self::persona('aca', 'aca@spims.test', 'Academic', 'Dean', true, 'bi-journal-bookmark', 'admin.programs', 'en', ['ACADEMIC_ADMIN']),
            self::persona('adm', 'adm@spims.test', 'Admin', 'Office', true, 'bi-building', 'admin.applications', 'en', ['ADMINISTRATIVE_ADMIN']),
            self::persona('fin', 'fin@spims.test', 'Finance', 'Bursar', true, 'bi-wallet2', 'admin.finance', 'en', ['FINANCIAL_ADMIN']),
            self::persona('ta1', 'ta1@spims.test', 'Yousef', 'Assistant', true, 'bi-people', 'teach', 'en', ['TA']),
            self::persona('dual', 'dual@spims.test', 'Dual', 'Role', true, 'bi-person-badge', 'teach', 'en', ['INSTRUCTOR', 'STUDENT']),
            self::persona('ins2', 'ins2@spims.test', 'Mariana', 'Teacher', false, 'bi-easel', 'teach', 'ar', ['INSTRUCTOR']),
            self::persona('student2', 'student2@spims.test', 'Sara', 'Habib', false, 'bi-person', 'dashboard', 'ar', ['STUDENT']),
            self::persona('student3', 'student3@spims.test', 'Mark', 'Shenouda', false, 'bi-person', 'dashboard', 'en', ['STUDENT']),
            self::persona('student4', 'student4@spims.test', 'Mary', 'Guirguis', false, 'bi-person', 'dashboard', 'fr', ['STUDENT']),
            self::persona('student5', 'student5@spims.test', 'David', 'Bishoy', false, 'bi-person', 'dashboard', 'en', ['STUDENT']),
            self::persona('student7', 'student7@spims.test', 'Peter', 'Atallah', false, 'bi-person', 'dashboard', 'en', ['STUDENT']),
            self::persona('student8', 'student8@spims.test', 'Rebecca', 'Fawzy', false, 'bi-person', 'dashboard', 'en', ['STUDENT']),
            self::persona('student9', 'student9@spims.test', 'Andrew', 'Naguib', false, 'bi-person', 'dashboard', 'en', ['STUDENT']),
            self::persona('student10', 'student10@spims.test', 'Christine', 'Wahba', false, 'bi-person', 'dashboard', 'ar', ['STUDENT']),
        ];
    }

    /**
     * @return array{
     *     slug: string,
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     featured: bool,
     *     icon: string,
     *     landing: string,
     *     locale: string,
     *     roles: list<string>
     * }|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $persona) {
            if ($persona['slug'] === $slug) {
                return $persona;
            }
        }

        return null;
    }

    /**
     * @param  array{landing: string}  $persona
     */
    public static function landingUrl(array $persona): string
    {
        return match ($persona['landing']) {
            'learn.th101' => self::offeringRoute('learn.offering', 'TH101') ?? route('dashboard'),
            'teach.th101' => self::offeringRoute('teach.show', 'TH101') ?? route('teach.index'),
            'teach' => route('teach.index'),
            'admin.applications' => route('admin.applications.index'),
            'admin.programs' => route('admin.programs.index'),
            'admin.finance' => route('admin.finance.index'),
            default => route('dashboard'),
        };
    }

    /**
     * @return array{
     *     slug: string,
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     featured: bool,
     *     icon: string,
     *     landing: string,
     *     locale: string,
     *     roles: list<string>
     * }
     */
    private static function persona(
        string $slug,
        string $email,
        string $first,
        string $last,
        bool $featured,
        string $icon,
        string $landing,
        string $locale,
        array $roles,
    ): array {
        return [
            'slug' => $slug,
            'email' => $email,
            'first_name' => $first,
            'last_name' => $last,
            'featured' => $featured,
            'icon' => $icon,
            'landing' => $landing,
            'locale' => $locale,
            'roles' => $roles,
        ];
    }

    private static function offeringRoute(string $route, string $courseCode): ?string
    {
        $offering = CourseOffering::query()
            ->whereHas('course', fn ($query) => $query->where('code', $courseCode))
            ->whereIn('status', [OfferingStatus::Open, OfferingStatus::InProgress])
            ->orderBy('created_at')
            ->first();

        if ($offering === null) {
            return null;
        }

        return route($route, $offering);
    }
}
