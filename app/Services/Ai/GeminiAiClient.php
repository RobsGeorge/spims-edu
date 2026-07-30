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
