<?php

namespace App\Services\Import;

use App\Services\Ai\AiClient;
use Illuminate\Support\Collection;

/**
 * L8, Part B — the AI-assisted mapping tier. See
 * docs/legacy-data-import-plan.md §5.5 and §22.3.
 *
 * A fourth, optional tier on top of `ImportMappingSuggestionService`'s three
 * deterministic ones, composed rather than folded into that class so the always-on
 * deterministic path (§5.2) and this off-by-default, network-calling one stay
 * separately readable and separately testable. Three hard constraints, each with its
 * own test in `ImportAiMappingTest`:
 *
 * 1. Never calls the AI client at all unless `import.ai_mapping_enabled` is true —
 *    checked here, before anything is built, not left to the client's own graceful
 *    degradation. A disabled setting means zero network activity, not a call that
 *    happens to no-op.
 * 2. Only ever proposes a target field for a column the deterministic tiers left at
 *    `None` confidence — `fillGaps()` never touches a column already carrying a
 *    High/Medium/Low match. AI fills gaps; it never overrides a match a cheaper,
 *    deterministic rule already made with more precision than an LLM guess could.
 * 3. The outbound payload (`buildSchema()`) carries column headers, inferred types,
 *    null percentages, distinct counts and `ImportAiMasking`'s fixed, type-derived
 *    placeholder samples only — never a real value from the sheet. See
 *    `ImportAiMasking`'s own docblock for why this is true by construction.
 *
 * Every suggestion this tier proposes still lands in the same `mapping` array the
 * registrar reviews and edits on the normal mapping screen before Continue — nothing
 * here writes past that array. `confidence` on an AI-sourced entry is never trusted
 * as-is unless it is one of the three real confidence levels; `origin: 'AI'` and
 * `rationale` mark it for the mapping screen to badge distinctly from a heuristic one.
 */
class ImportAiMappingSuggester
{
    public function __construct(private readonly AiClient $ai) {}

    /**
     * @param  array<int, array{column: string, target_field: ?string, transform: string, confidence?: string, options?: array<string, mixed>, ignored?: bool, origin?: string, rationale?: string}>  $mapping
     * @param  array<int, array{column: string, type: string, empty_percent: float, distinct_count: int, samples: array<int, string>}>  $profile
     * @param  array<string, string>  $targetCatalog
     * @return array<int, array{column: string, target_field: ?string, transform: string, confidence?: string, options?: array<string, mixed>, ignored?: bool, origin?: string, rationale?: string}>
     */
    public function fillGaps(array $mapping, array $profile, string $sourceCode, array $targetCatalog): array
    {
        if (! config('import.ai_mapping_enabled')) {
            // Constraint 3: off unless explicitly switched on — not even a no-op call.
            return $mapping;
        }

        $gaps = collect($mapping)->filter(
            fn (array $m) => ($m['confidence'] ?? 'None') === 'None' && empty($m['ignored'])
        );

        if ($gaps->isEmpty()) {
            return $mapping;
        }

        $profileByColumn = collect($profile)->keyBy('column');
        $schema = $this->buildSchema($gaps, $profileByColumn, $sourceCode, $targetCatalog);

        $response = $this->ai->suggestFieldMapping($schema);
        if (! is_array($response) || ! isset($response['suggestions']) || ! is_array($response['suggestions'])) {
            // Degrade gracefully: a null or malformed response leaves every
            // deterministic suggestion exactly as it was.
            return $mapping;
        }

        $bySuggestedColumn = collect($response['suggestions'])
            ->filter(fn ($s) => is_array($s) && isset($s['column']))
            ->keyBy('column');
        $validTargets = array_keys($targetCatalog);
        $validConfidences = ['High', 'Medium', 'Low'];

        return collect($mapping)->map(function (array $m) use ($bySuggestedColumn, $validTargets, $validConfidences) {
            // Constraint 2: never override an existing confident match.
            if (($m['confidence'] ?? 'None') !== 'None' || ! empty($m['ignored'])) {
                return $m;
            }

            $suggestion = $bySuggestedColumn->get($m['column']);
            $target = $suggestion['target_field'] ?? null;
            if ($suggestion === null || $target === null || ! in_array($target, $validTargets, true)) {
                return $m;
            }

            return array_merge($m, [
                'target_field' => $target,
                'confidence' => in_array($suggestion['confidence'] ?? null, $validConfidences, true)
                    ? $suggestion['confidence']
                    : 'Low',
                'transform' => $m['transform'] ?? 'trim',
                'origin' => 'AI',
                'rationale' => (string) ($suggestion['rationale'] ?? ''),
            ]);
        })->values()->all();
    }

    /**
     * The exact allowlisted outbound shape — see the class docblock and §22.3.
     * Keys here are the complete set ImportAiMappingTest checks against.
     *
     * @param  Collection<int, array<string, mixed>>  $gaps
     * @param  Collection<string, array<string, mixed>>  $profileByColumn
     * @param  array<string, string>  $targetCatalog
     * @return array{source: string, target_fields: array<int, array{key: string, label: string}>, columns: array<int, array{column: string, type: string, empty_percent: float, distinct_count: int, masked_samples: array<int, string>}>}
     */
    private function buildSchema(
        Collection $gaps,
        Collection $profileByColumn,
        string $sourceCode,
        array $targetCatalog,
    ): array {
        return [
            'source' => $sourceCode,
            'target_fields' => collect($targetCatalog)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'columns' => $gaps->map(function (array $m) use ($profileByColumn) {
                $prof = $profileByColumn->get($m['column'], []);
                $type = $prof['type'] ?? 'text';

                return [
                    'column' => $m['column'],
                    'type' => $type,
                    'empty_percent' => (float) ($prof['empty_percent'] ?? 0.0),
                    'distinct_count' => (int) ($prof['distinct_count'] ?? 0),
                    // ImportAiMasking never reads $prof['samples'] — the real values —
                    // at all. See that class's docblock.
                    'masked_samples' => ImportAiMasking::samplesForType($type),
                ];
            })->values()->all(),
        ];
    }
}
