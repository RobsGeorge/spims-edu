<?php

namespace App\Services\Import;

use App\Enums\ImportPopulation;

/**
 * The target field catalog for a STUDENT batch, and which fields are required for
 * which population. Kept as one source of truth so the mapping screen's guard, the
 * validator, and the committer can never disagree about what "required" means.
 * See docs/legacy-data-import-plan.md §11.5 and D8.
 */
class ImportStudentFields
{
    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            'legacy_id' => 'Legacy id',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'phone' => 'Phone',
            'date_of_birth' => 'Date of birth',
            'country_code' => 'Country',
            'preferred_locale' => 'Preferred locale',
            'program_code' => 'Program code (matched against the live catalog only)',
        ];
    }

    /**
     * Fields that must be produced by at least one mapping, regardless of population.
     *
     * @return array<int, string>
     */
    public static function alwaysRequired(): array
    {
        return ['legacy_id', 'first_name', 'last_name'];
    }

    /**
     * @return array<int, string>
     */
    public static function requiredFor(?ImportPopulation $population): array
    {
        $required = self::alwaysRequired();

        if ($population === ImportPopulation::Active) {
            $required[] = 'email';
        }

        return $required;
    }
}
