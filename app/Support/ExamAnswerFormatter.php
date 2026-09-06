<?php

namespace App\Support;

use App\Models\AttemptAnswer;
use App\Models\Question;

class ExamAnswerFormatter
{
    public static function studentResponse(?AttemptAnswer $answer): string
    {
        if ($answer === null || ! is_array($answer->response)) {
            return '—';
        }

        $response = $answer->response;
        if (isset($response['text'])) {
            return (string) $response['text'];
        }
        if (isset($response['value'])) {
            return (string) $response['value'];
        }
        if (isset($response['filename'])) {
            return (string) $response['filename'];
        }
        if (isset($response['option_id'])) {
            return (string) ($answer->question?->options->firstWhere('id', $response['option_id'])?->text ?? $response['option_id']);
        }
        if (isset($response['option_ids']) && is_array($response['option_ids'])) {
            $labels = $answer->question?->options
                ->whereIn('id', $response['option_ids'])
                ->pluck('text')
                ->all() ?? $response['option_ids'];

            return implode(', ', $labels);
        }
        if (isset($response['matches']) && is_array($response['matches'])) {
            $parts = [];
            foreach ($response['matches'] as $optionId => $key) {
                $label = $answer->question?->options->firstWhere('id', $optionId)?->text ?? $optionId;
                $parts[] = $label.' → '.$key;
            }

            return implode('; ', $parts);
        }
        if (isset($response['order']) && is_array($response['order'])) {
            $labels = [];
            foreach ($response['order'] as $optionId) {
                $labels[] = $answer->question?->options->firstWhere('id', $optionId)?->text ?? $optionId;
            }

            return implode(' → ', $labels);
        }

        return '—';
    }

    public static function correctAnswer(?Question $question): string
    {
        if ($question === null) {
            return '—';
        }

        $question->loadMissing('options');

        return match ($question->type->value) {
            'MCQ_SINGLE', 'TRUE_FALSE' => (string) ($question->options->firstWhere('is_correct', true)?->text ?? '—'),
            'MCQ_MULTI' => $question->options->where('is_correct', true)->pluck('text')->implode(', ') ?: '—',
            'MATCHING' => $question->options
                ->filter(fn ($o) => $o->match_key !== null)
                ->map(fn ($o) => $o->text.' → '.$o->match_key)
                ->implode('; ') ?: '—',
            'ORDERING' => $question->options->sortBy('order')->pluck('text')->implode(' → ') ?: '—',
            'NUMERIC' => (string) ($question->config['correct_value'] ?? '—'),
            'FILL_BLANK', 'SHORT_ANSWER' => implode(', ', $question->config['accepted_answers'] ?? []) ?: ($question->options->where('is_correct', true)->pluck('text')->implode(', ') ?: '—'),
            default => '—',
        };
    }
}
