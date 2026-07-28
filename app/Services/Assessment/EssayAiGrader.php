<?php

namespace App\Services\Assessment;

use App\Models\Question;
use Illuminate\Support\Facades\Log;

class EssayAiGrader
{
    /**
     * @return array{score: float, rationale: string}|null
     */
    public function suggest(Question $question, string $essayText, float $maxPoints): ?array
    {
        if (empty(config('services.gemini.key'))) {
            Log::info('AI essay grading skipped — GOOGLE_API_KEY not configured', [
                'question_id' => $question->id,
            ]);

            return null;
        }

        // Placeholder Gemini call — returns a heuristic until real SDK wiring.
        $keyPoints = array_filter(array_map('trim', preg_split('/[\n,;]+/', (string) $question->ai_key_points) ?: []));
        $hits = 0;
        $lower = mb_strtolower($essayText);
        foreach ($keyPoints as $point) {
            if ($point !== '' && str_contains($lower, mb_strtolower($point))) {
                $hits++;
            }
        }

        $ratio = $keyPoints === [] ? 0.7 : ($hits / count($keyPoints));
        $score = round(min($maxPoints, $maxPoints * $ratio), 2);

        return [
            'score' => $score,
            'rationale' => 'AI heuristic matched '.$hits.'/'.max(1, count($keyPoints)).' key points. '.$question->ai_guidance,
        ];
    }
}
