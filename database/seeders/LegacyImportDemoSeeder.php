<?php

namespace Database\Seeders;

use App\Models\ImportGradeMapping;
use App\Models\ImportSource;
use Illuminate\Database\Seeder;

/**
 * Two import sources (Populi, Canvas) with a starter grade-conversion table, so the
 * legacy-import wizard (docs/legacy-data-import-plan.md) has something to upload
 * against out of the box on a fresh environment. Entirely additive and idempotent
 * (`updateOrCreate` throughout) — safe to re-run.
 *
 * Pair this with the demo CSVs in docs/legacy-import-demo/ to exercise every entity
 * type (STUDENT, COURSE_RESULT, BALANCE, MIDTERM_ENROLLMENT, CREDENTIAL) against real
 * DemoDataSeeder records (courses, programs, the TH101/Fall live offering and its
 * gradebook components).
 */
class LegacyImportDemoSeeder extends Seeder
{
    public function run(): void
    {
        $populi = ImportSource::query()->updateOrCreate(
            ['code' => 'POPULI'],
            [
                'name' => 'Populi',
                'kind' => 'SIS',
                'precedence' => 1,
                'gpa_scale_max' => 4.00,
                'default_currency' => 'EGP',
                'timezone' => 'Africa/Cairo',
                'active' => true,
            ]
        );

        ImportSource::query()->updateOrCreate(
            ['code' => 'CANVAS'],
            [
                'name' => 'Canvas',
                'kind' => 'LMS',
                'precedence' => 2,
                'gpa_scale_max' => 4.00,
                'default_currency' => 'EGP',
                'timezone' => 'Africa/Cairo',
                'active' => true,
            ]
        );

        $grades = [
            ['A', 95.0, 100.0, 'A', 4.0, true],
            ['B', 85.0, 94.99, 'B', 3.0, true],
            ['C', 75.0, 84.99, 'C', 2.0, true],
            ['F', 0.0, 59.99, 'F', 0.0, false],
        ];

        foreach ($grades as [$letter, $min, $max, $spims, $points, $passing]) {
            ImportGradeMapping::query()->updateOrCreate(
                ['source_id' => $populi->id, 'legacy_letter' => $letter],
                [
                    'min_percent' => $min,
                    'max_percent' => $max,
                    'spims_letter' => $spims,
                    'gpa_points' => $points,
                    'is_passing' => $passing,
                    'counts_toward_gpa' => false,
                ]
            );
        }
    }
}
