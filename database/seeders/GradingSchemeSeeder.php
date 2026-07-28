<?php

namespace Database\Seeders;

use App\Models\GradingScheme;
use App\Models\GradeBand;
use Illuminate\Database\Seeder;

class GradingSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $scheme = GradingScheme::query()->updateOrCreate(
            ['name' => 'SPIMS Default'],
            ['is_default' => true]
        );

        $bands = [
            ['letter' => 'A',  'min_percent' => 90, 'max_percent' => 100, 'gpa_points' => 4.0, 'is_passing' => true],
            ['letter' => 'B',  'min_percent' => 80, 'max_percent' => 89.99, 'gpa_points' => 3.0, 'is_passing' => true],
            ['letter' => 'C',  'min_percent' => 70, 'max_percent' => 79.99, 'gpa_points' => 2.0, 'is_passing' => true],
            ['letter' => 'D',  'min_percent' => 60, 'max_percent' => 69.99, 'gpa_points' => 1.0, 'is_passing' => true],
            ['letter' => 'F',  'min_percent' => 0,  'max_percent' => 59.99, 'gpa_points' => 0.0, 'is_passing' => false],
        ];

        foreach ($bands as $band) {
            GradeBand::query()->updateOrCreate(
                ['scheme_id' => $scheme->id, 'letter' => $band['letter']],
                $band
            );
        }
    }
}
