<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiClient implements AiClient
{
    private const GENERATE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function translate(string $text, string $source, string $target): ?string
    {
        $key = $this->apiKey();
        if ($key === null) {
            return null;
        }

        $prompt = sprintf(
            'Translate the following text from %s to %s. Return only the translation, no commentary.\n\n%s',
            $source,
            $target,
            $text
        );

        $raw = $this->generateText($key, $prompt);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return trim($raw);
    }

    public function suggestEssayScore(string $prompt): ?array
    {
        $key = $this->apiKey();
        if ($key === null) {
            return null;
        }

        $instruction = $prompt."\n\nRespond with JSON only: {\"score\": <number>, \"rationale\": \"<string>\"}";
        $raw = $this->generateText($key, $instruction);
        if ($raw === null) {
            return null;
        }

        if (preg_match('/\{.*\}/s', $raw, $matches) !== 1) {
            Log::warning('Gemini essay response was not JSON', ['snippet' => mb_substr($raw, 0, 200)]);

            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($matches[0], true);
        if (! is_array($decoded) || ! isset($decoded['score'], $decoded['rationale'])) {
            return null;
        }

        return [
            'score' => (float) $decoded['score'],
            'rationale' => (string) $decoded['rationale'],
        ];
    }

    /**
     * L8, Part B — mirrors suggestEssayScore()'s degrade-gracefully / parse-JSON /
     * never-throw shape exactly: no key, no suggestions; a non-2xx response, a
     * non-JSON body, or JSON missing the expected keys all degrade to null rather than
     * throwing. `$schema` is already the fully masked, allowlisted payload built by
     * `ImportAiMappingSuggester` — this method does not touch or re-derive it.
     *
     * @param  array<string, mixed>  $schema
     * @return array{suggestions: array<int, array{column: string, target_field: ?string, confidence: string, rationale: string}>}|null
     */
    public function suggestFieldMapping(array $schema): ?array
    {
        $key = $this->apiKey();
        if ($key === null) {
            return null;
        }

        $instruction = implode("\n", [
            'You are mapping spreadsheet columns from a legacy school records export to a fixed set of target fields.',
            'Only column headers, inferred types and masked example shapes are given below — no real student data.',
            'For each column, suggest the single best target_field (or null if none fits), a confidence of "High", "Medium" or "Low", and a one-sentence rationale.',
            'Schema: '.json_encode($schema),
            'Respond with JSON only, matching exactly: {"suggestions": [{"column": "<string>", "target_field": "<string or null>", "confidence": "<High|Medium|Low>", "rationale": "<string>"}]}',
        ]);

        $raw = $this->generateText($key, $instruction);
        if ($raw === null) {
            return null;
        }

        if (preg_match('/\{.*\}/s', $raw, $matches) !== 1) {
            Log::warning('Gemini field-mapping response was not JSON', ['snippet' => mb_substr($raw, 0, 200)]);

            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($matches[0], true);
        if (! is_array($decoded) || ! isset($decoded['suggestions']) || ! is_array($decoded['suggestions'])) {
            return null;
        }

        $suggestions = [];
        foreach ($decoded['suggestions'] as $entry) {
            if (! is_array($entry) || ! isset($entry['column'])) {
                continue;
            }
            $suggestions[] = [
                'column' => (string) $entry['column'],
                'target_field' => isset($entry['target_field']) && $entry['target_field'] !== '' ? (string) $entry['target_field'] : null,
                'confidence' => (string) ($entry['confidence'] ?? 'Low'),
                'rationale' => (string) ($entry['rationale'] ?? ''),
            ];
        }

        return ['suggestions' => $suggestions];
    }

    private function apiKey(): ?string
    {
        $key = config('services.gemini.key');

        return filled($key) ? (string) $key : null;
    }

    private function generateText(string $key, string $prompt): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withQueryParameters(['key' => $key])
                ->post(self::GENERATE_URL, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini API request failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            return is_string($text) ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('Gemini API call error', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
