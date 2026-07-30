<?php

namespace App\Services\Ai;

interface AiClient
{
    public function translate(string $text, string $source, string $target): ?string;

    /**
     * @return array{score: float, rationale: string}|null
     */
    public function suggestEssayScore(string $prompt): ?array;
}
