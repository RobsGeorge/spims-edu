<?php

namespace App\Services\Assessment;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;

class ObjectiveGrader
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function score(Question $question, ?array $response, float $maxPoints): ?float
    {
        if ($response === null) {
            return 0.0;
        }

        $question->loadMissing('options');

        return match ($question->type) {
            QuestionType::McqSingle, QuestionType::TrueFalse => $this->scoreSingle($question, $response, $maxPoints),
            QuestionType::McqMulti => $this->scoreMulti($question, $response, $maxPoints),
            QuestionType::Numeric => $this->scoreNumeric($question, $response, $maxPoints),
            QuestionType::FillBlank, QuestionType::ShortAnswer => $this->scoreText($question, $response, $maxPoints),
            QuestionType::Matching => $this->scoreMatching($question, $response, $maxPoints),
            QuestionType::Ordering => $this->scoreOrdering($question, $response, $maxPoints),
            default => null, // essay / file — manual
        };
    }

    private function scoreSingle(Question $question, array $response, float $maxPoints): float
    {
        $selected = (string) ($response['option_id'] ?? '');
        $correct = $question->options->firstWhere('is_correct', true);

        return ($correct && $correct->id === $selected) ? $maxPoints : 0.0;
    }

    private function scoreMulti(Question $question, array $response, float $maxPoints): float
    {
        $selected = collect($response['option_ids'] ?? [])->map(fn ($id) => (string) $id)->sort()->values()->all();
        $correct = $question->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();

        return $selected === $correct ? $maxPoints : 0.0;
    }

    private function scoreNumeric(Question $question, array $response, float $maxPoints): float
    {
        $expected = (float) ($question->config['correct_value'] ?? 0);
        $tolerance = (float) ($question->config['tolerance'] ?? 0);
        $given = (float) ($response['value'] ?? NAN);

        if (is_nan($given)) {
            return 0.0;
        }

        return abs($given - $expected) <= $tolerance ? $maxPoints : 0.0;
    }

    private function scoreText(Question $question, array $response, float $maxPoints): ?float
    {
        $given = mb_strtolower(trim((string) ($response['text'] ?? '')));
        if ($given === '') {
            return 0.0;
        }

        $accepted = collect($question->config['accepted_answers'] ?? [])
            ->map(fn ($a) => mb_strtolower(trim((string) $a)))
            ->filter()
            ->all();

        if ($accepted === []) {
            $accepted = $question->options->where('is_correct', true)
                ->map(fn (QuestionOption $o) => mb_strtolower(trim($o->text)))
                ->all();
        }

        if ($accepted === []) {
            return null; // needs manual
        }

        return in_array($given, $accepted, true) ? $maxPoints : 0.0;
    }

    private function scoreMatching(Question $question, array $response, float $maxPoints): float
    {
        $map = $response['matches'] ?? [];
        if (! is_array($map) || $map === []) {
            return 0.0;
        }

        $correct = 0;
        $total = 0;
        foreach ($question->options as $option) {
            if ($option->match_key === null) {
                continue;
            }
            $total++;
            if (($map[$option->id] ?? null) === $option->match_key) {
                $correct++;
            }
        }

        if ($total === 0) {
            return 0.0;
        }

        return round($maxPoints * ($correct / $total), 2);
    }

    private function scoreOrdering(Question $question, array $response, float $maxPoints): float
    {
        $order = collect($response['order'] ?? [])->map(fn ($id) => (string) $id)->values()->all();
        $expected = $question->options->sortBy('order')->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        return $order === $expected ? $maxPoints : 0.0;
    }
}
