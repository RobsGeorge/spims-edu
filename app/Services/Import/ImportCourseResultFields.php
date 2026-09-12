<?php

namespace App\Services\Import;

/**
 * The target field catalog for a COURSE_RESULT batch. Mirrors ImportStudentFields's
 * shape (§11.5) — one source of truth for what the mapping screen offers, what
 * validate() requires, and what the committer reads. See
 * docs/legacy-data-import-plan.md §7 and the L3 task brief.
 *
 * Deliberately absent, same rule as ImportStudentFields: a legacy summary GPA,
 * honors, or credits-earned figure is not offered here — those belong to
 * `legacy_academic_summaries` and are not yet wired to the mapping engine.
 */
class ImportCourseResultFields
{
    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            'legacy_id' => 'Legacy student id (must already be linked from a STUDENT batch)',
            'course_code' => 'Course code',
            'course_title' => 'Course title (used only if a shadow course is created)',
            'term' => 'Term (free text, stored verbatim)',
            'legacy_letter' => 'Source letter grade',
            'legacy_percent' => 'Source percent',
            'credit_hours' => 'Credit hours (kept from the source)',
            'program_code' => 'Program code (matched against the live catalog only)',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function alwaysRequired(): array
    {
        return ['legacy_id', 'course_code', 'term', 'legacy_letter', 'credit_hours'];
    }

    /**
     * Population never varies what's required for a course result row — kept as a
     * parameter only so callers that iterate both field catalogs stay uniform.
     *
     * @return array<int, string>
     */
    public static function requiredFor(mixed $population = null): array
    {
        return self::alwaysRequired();
    }
}
