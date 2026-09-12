<?php

namespace App\Services\Ai;

interface AiClient
{
    public function translate(string $text, string $source, string $target): ?string;

    /**
     * @return array{score: float, rationale: string}|null
     */
    public function suggestEssayScore(string $prompt): ?array;

    /**
     * L8, Part B — proposes a target-field mapping for the columns an import
     * mapping's deterministic tiers (§5.2) left unmapped. `$schema` never carries a
     * raw sample value — see `ImportAiMasking` and
     * docs/legacy-data-import-plan.md §22.3 for the exact allowlisted shape and why.
     *
     * @param  array<string, mixed>  $schema
     * @return array{suggestions: array<int, array{column: string, target_field: ?string, confidence: string, rationale: string}>}|null
     */
    public function suggestFieldMapping(array $schema): ?array;
}
