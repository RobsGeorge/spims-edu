<?php

namespace App\Services\Reports;

use App\Enums\AcademicStanding;
use App\Models\Setting;
use App\Models\StudentProgram;

class AcademicStandingService
{
    public const SETTING_KEY = 'academic_standing.thresholds';

    /** Good standing at or above this GPA, stored as hundredths (200 = 2.00). */
    public const DEFAULT_GOOD_MIN = 200;

    /** Suspension below this GPA, stored as hundredths (100 = 1.00). */
    public const DEFAULT_SUSPENSION_BELOW = 100;

    /**
     * @return array{good_min: int, suspension_below: int}
     */
    public function thresholds(): array
    {
        $value = Setting::query()->find(self::SETTING_KEY)?->value ?? [];

        return [
            'good_min' => (int) ($value['good_min'] ?? self::DEFAULT_GOOD_MIN),
            'suspension_below' => (int) ($value['suspension_below'] ?? self::DEFAULT_SUSPENSION_BELOW),
        ];
    }

    public function apply(StudentProgram $sp): void
    {
        if ($sp->cached_gpa === null) {
            if ($sp->academic_standing !== null) {
                $sp->update(['academic_standing' => null]);
            }

            return;
        }

        $standing = $this->resolve((float) $sp->cached_gpa);
        if ($sp->academic_standing !== $standing) {
            $sp->update(['academic_standing' => $standing]);
        }
    }

    public function resolve(float $gpa): AcademicStanding
    {
        $hundredths = (int) round($gpa * 100);
        $thresholds = $this->thresholds();

        if ($hundredths < $thresholds['suspension_below']) {
            return AcademicStanding::Suspension;
        }

        if ($hundredths < $thresholds['good_min']) {
            return AcademicStanding::Probation;
        }

        return AcademicStanding::Good;
    }
}
