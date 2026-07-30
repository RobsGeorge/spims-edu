<?php

namespace App\Services\Assessment;

use App\Models\Question;
use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Log;

class EssayAiGrader
{
    public function __construct(
        private readonly AiClient $ai,
    ) {}

    /**
     * @return array{score: float, rationale: string}|null
     */
    public function suggest(Question $question, string $essayText, float $maxPoints): ?array
    {
        $keyPoints = array_values(array_filter(array_map(
            'trim',
            preg_split('/[\n,;]+/', (string) $question->ai_key_points) ?: []
        )));

        $prompt = implode("\n", [
            'Score the following essay response.',
            'Question: '.$question->prompt,
            'Max points: '.$maxPoints,
            'Key points to look for: '.($keyPoints === [] ? '(none specified)' : implode(', ', $keyPoints)),
            'Guidance: '.(string) $question->ai_guidance,
            'Essay: '.$essayText,
            'Return a numeric score between 0 and '.$maxPoints.'.',
        ]);

        $suggestion = $this->ai->suggestEssayScore($prompt);
        if ($suggestion === null) {
            Log::info('AI essay grading skipped — AiClient returned null', [
                'question_id' => $question->id,
            ]);

            return null;
        }

        $score = max(0.0, min($maxPoints, (float) $suggestion['score']));

        return [
            'score' => round($score, 2),
            'rationale' => (string) $suggestion['rationale'],
        ];
    }
}
