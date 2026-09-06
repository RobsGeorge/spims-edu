<?php

namespace App\Services\LiveQuiz;

/**
 * Integer live-quiz score. Server timestamps only — callers pass
 * remaining = question_closes_at - answered_at (unix seconds).
 *
 * Correct and on-time: base points + floor(points * remaining / time_limit).
 * Remaining is clamped to [0, time_limit]. Instant answer → nearly 2× points;
 * answer at the closing instant → base points. Late and wrong scores are 0
 * and are applied by LiveQuizPlayService, not here.
 */
final class LiveQuizScoring
{
    public static function correctScore(int $points, int $timeLimitSeconds, int $remainingSeconds): int
    {
        if ($points <= 0) {
            return 0;
        }

        if ($timeLimitSeconds <= 0) {
            return $points;
        }

        $remaining = max(0, min($remainingSeconds, $timeLimitSeconds));

        return $points + intdiv($points * $remaining, $timeLimitSeconds);
    }
}
