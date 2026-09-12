<?php

namespace App\Services\Import;

/**
 * The target field catalog for a MIDTERM_ENROLLMENT batch (L7 — the conditional
 * mid-term cutover, V2). Mirrors ImportCourseResultFields's shape — one source of
 * truth for what the mapping screen offers, what validate() requires, and what the
 * committer reads. See docs/legacy-data-import-plan.md §22.
 *
 * One row = one (student, offering, component) score — the "long" format decision
 * #4 in the task brief. `course_code` + `semester_name` are always required and
 * resolve the row's target live CourseOffering together (decision #2); `offering_id`
 * is an optional escape hatch that takes precedence when course_code + semester_name
 * resolve to more than one live offering (e.g. two sections of the same course in the
 * same semester) — see §22.2. Deliberately absent: anything that would create or
 * update a program, a shadow course, or a final grade — this entity type only ever
 * touches a live offering a registrar already built.
 */
class ImportMidtermEnrollmentFields
{
    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            'legacy_id' => 'Legacy student id (must already be linked from a STUDENT batch)',
            'course_code' => 'Course code (of the already-built live offering)',
            'semester_name' => 'Semester name (of the already-built live offering)',
            'offering_id' => 'SPIMS offering id (optional — only needed if course code + semester match more than one live offering)',
            'component_name' => 'Gradebook component name (must already exist on that live offering)',
            'score' => 'Score, 0-100 (direct component percent override)',
        ];
    }

    /**
     * `offering_id` is deliberately not required — it is a disambiguator, not the
     * primary route to the offering. course_code + semester_name are required on
     * every row even when offering_id is also given, so the mapping screen's
     * required-field guard stays a simple, uniform AND set rather than inventing
     * per-row OR-required logic nothing else in this feature has. See §22.2.
     *
     * @return array<int, string>
     */
    public static function alwaysRequired(): array
    {
        return ['legacy_id', 'course_code', 'semester_name', 'component_name', 'score'];
    }

    /**
     * MIDTERM_ENROLLMENT has no population split — kept as a parameter only so
     * callers that iterate every field catalog stay uniform (same convention as
     * ImportCourseResultFields / ImportBalanceFields).
     *
     * @return array<int, string>
     */
    public static function requiredFor(mixed $population = null): array
    {
        return self::alwaysRequired();
    }
}
