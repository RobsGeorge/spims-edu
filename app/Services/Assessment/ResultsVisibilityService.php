<?php

namespace App\Services\Assessment;

use App\Enums\ResultsVisibility;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Learning\OfferingAccessService;

class ResultsVisibilityService
{
    public function __construct(
        private readonly OfferingAccessService $access,
    ) {}

    /**
     * Student-facing scores stay hidden until the visibility rule is satisfied.
     * ON_RELEASE (default) waits for S5 announceResults(); IMMEDIATE is after submit;
     * AFTER_CLOSE waits for closes_at. Staff may always see scores they grade.
     */
    public function scoresVisible(Assessment $assessment, ?User $viewer = null): bool
    {
        $assessment->loadMissing(['offering', 'resultAnnouncement']);

        if ($viewer && $assessment->offering && $this->access->isStaffOrAdmin($viewer, $assessment->offering)) {
            return true;
        }

        return match ($assessment->results_visibility) {
            ResultsVisibility::Immediate => true,
            ResultsVisibility::AfterClose => $assessment->closes_at !== null && now()->gte($assessment->closes_at),
            ResultsVisibility::OnRelease => $assessment->resultAnnouncement?->announced_at !== null,
        };
    }

    public function answersVisible(Assessment $assessment, ?User $viewer = null): bool
    {
        $assessment->loadMissing('offering');

        if ($viewer && $assessment->offering && $this->access->isStaffOrAdmin($viewer, $assessment->offering)) {
            return true;
        }

        return $assessment->reveal_answers && $this->scoresVisible($assessment, $viewer);
    }

    /**
     * Strip score fields from a student attempt payload when results are hidden.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redactStudentAttempt(Assessment $assessment, array $payload, User $viewer): array
    {
        $visible = $this->scoresVisible($assessment, $viewer);
        $payload['scores_visible'] = $visible;
        $payload['answers_visible'] = $this->answersVisible($assessment, $viewer);

        if ($visible) {
            return $payload;
        }

        $payload['total_score'] = null;
        if (isset($payload['answers']) && is_array($payload['answers'])) {
            $payload['answers'] = array_map(function ($answer) {
                if (is_array($answer)) {
                    $answer['final_score'] = null;
                    $answer['auto_score'] = null;
                }

                return $answer;
            }, $payload['answers']);
        }

        return $payload;
    }
}
