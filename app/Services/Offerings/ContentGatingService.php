<?php

namespace App\Services\Offerings;

use App\Enums\OfferingMode;
use App\Models\CourseOffering;
use App\Models\Week;
use Carbon\CarbonInterface;

class ContentGatingService
{
    /**
     * Public preview: Week 1 full items + titles of all weeks.
     */
    public function publicPreview(CourseOffering $offering): array
    {
        $weeks = $offering->weeks->sortBy('number')->values();

        return [
            'course' => [
                'code' => $offering->course->code,
                'title' => $offering->course->title,
            ],
            'mode' => $offering->mode->value,
            'week_titles' => $weeks->map(fn (Week $w) => [
                'number' => $w->number,
                'title' => $w->title,
            ])->all(),
            'week_one' => $weeks->firstWhere('number', 1)?->items->map(fn ($item) => [
                'type' => $item->type->value,
                'title' => $item->title,
                'vimeo_id' => $item->vimeo_id,
                'body' => $item->body,
                'file_url' => $item->file_url,
            ])->all() ?? [],
        ];
    }

    public function isWeekUnlocked(CourseOffering $offering, Week $week, bool $enrolled = false, array $completedWeekNumbers = [], ?CarbonInterface $now = null): bool
    {
        $now = $now ?? now();

        if (! $enrolled) {
            return $week->number === 1;
        }

        if ($offering->mode === OfferingMode::Cohort) {
            if ($week->unlock_date === null) {
                return true;
            }

            return $now->gte($week->unlock_date);
        }

        // Self-paced: unlock on prior completion (week 1 always open when enrolled).
        if ($week->number === 1) {
            return true;
        }

        return in_array($week->number - 1, $completedWeekNumbers, true);
    }
}
