<?php

namespace App\Services\Import;

/**
 * Proposes a target field, transform and confidence for each sheet column, in three
 * tiers per docs/legacy-data-import-plan.md §5.2: an exact synonym match (High), a
 * normalised-header match (Medium), and a value-shape heuristic (Low). The registrar
 * approves or changes every suggestion on the mapping screen — this never applies
 * anything on its own.
 */
class ImportMappingSuggestionService
{
    /**
     * Per-source synonym dictionary, keyed by the source's `code`. Seeded with the real
     * Populi and Canvas export headers. A source with no dictionary falls back to the
     * generic one, so a school-specific spreadsheet still gets Medium/Low suggestions.
     *
     * @var array<string, array<string, string>>
     */
    private const SYNONYMS = [
        'POPULI' => [
            'populi id' => 'legacy_id',
            'person id' => 'legacy_id',
            'student id' => 'legacy_id',
            'last, first' => 'name_split',
            'name' => 'name_split',
            'full name' => 'name_split',
            'email' => 'email',
            'email address' => 'email',
            'mobile' => 'phone',
            'phone' => 'phone',
            'mobile phone' => 'phone',
            'birthdate' => 'date_of_birth',
            'birth date' => 'date_of_birth',
            'date of birth' => 'date_of_birth',
            'country' => 'country_code',
            'nationality' => 'country_code',
        ],
        'CANVAS' => [
            'sis user id' => 'legacy_id',
            'sis login id' => 'legacy_id',
            'user id' => 'legacy_id',
            'name' => 'name_split',
            'sortable name' => 'name_split',
            'email' => 'email',
            'login id' => 'email',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const GENERIC_SYNONYMS = [
        'id' => 'legacy_id',
        'legacy id' => 'legacy_id',
        'name' => 'name_split',
        'first name' => 'first_name',
        'last name' => 'last_name',
        'surname' => 'last_name',
        'given name' => 'first_name',
        'email' => 'email',
        'e-mail' => 'email',
        'phone' => 'phone',
        'mobile' => 'phone',
        'dob' => 'date_of_birth',
        'birthdate' => 'date_of_birth',
        'country' => 'country_code',
    ];

    /**
     * @param  array<int, array{column: string, type: string, empty_percent: float, distinct_count: int, samples: array<int, string>}>  $profile
     * @return array<int, array{column: string, target_field: ?string, transform: string, confidence: string, options: array<string, mixed>}>
     */
    public function suggest(array $profile, string $sourceCode): array
    {
        $dictionary = self::SYNONYMS[$sourceCode] ?? [];
        $suggestions = [];

        foreach ($profile as $col) {
            $suggestions[] = $this->suggestOne($col, $dictionary);
        }

        return $suggestions;
    }

    /**
     * @param  array{column: string, type: string, empty_percent: float, distinct_count: int, samples: array<int, string>}  $col
     * @param  array<string, string>  $dictionary
     * @return array{column: string, target_field: ?string, transform: string, confidence: string, options: array<string, mixed>}
     */
    private function suggestOne(array $col, array $dictionary): array
    {
        $normalized = $this->normalizeHeader($col['column']);

        // Tier 1: exact synonym match against the source's own dictionary.
        if (isset($dictionary[$normalized])) {
            return $this->resolve($col, $dictionary[$normalized], 'High');
        }

        // Tier 2: normalised-header match against the generic dictionary.
        if (isset(self::GENERIC_SYNONYMS[$normalized])) {
            return $this->resolve($col, self::GENERIC_SYNONYMS[$normalized], 'Medium');
        }

        // Tier 3: value-shape heuristics.
        if ($col['type'] === 'email') {
            return $this->resolve($col, 'email', 'Low');
        }
        if ($col['type'] === 'date' && str_contains($normalized, 'birth')) {
            return $this->resolve($col, 'date_of_birth', 'Low');
        }
        if ($col['type'] === 'integer' && str_contains($normalized, 'id')) {
            return $this->resolve($col, 'legacy_id', 'Low');
        }

        return [
            'column' => $col['column'],
            'target_field' => null,
            'transform' => 'none',
            'confidence' => 'None',
            'options' => [],
        ];
    }

    /**
     * @param  array{column: string, type: string, empty_percent: float, distinct_count: int, samples: array<int, string>}  $col
     * @return array{column: string, target_field: ?string, transform: string, confidence: string, options: array<string, mixed>}
     */
    private function resolve(array $col, string $target, string $confidence): array
    {
        if ($target === 'name_split') {
            // A name column maps to first_name by default — the admin retargets the
            // second half to last_name explicitly, since one sheet column producing two
            // target fields needs two mapping rows.
            $format = str_contains($col['samples'][0] ?? '', ',') ? 'last_first' : 'first_last';

            return [
                'column' => $col['column'],
                'target_field' => 'first_name',
                'transform' => 'name_part_first',
                'confidence' => $confidence,
                'options' => ['format' => $format],
            ];
        }

        if ($target === 'date_of_birth') {
            return [
                'column' => $col['column'],
                'target_field' => $target,
                'transform' => 'date',
                'confidence' => $confidence,
                'options' => ['format' => $this->guessDateFormat($col['samples'])],
            ];
        }

        $transform = $target === 'email' ? 'lower' : 'trim';

        return [
            'column' => $col['column'],
            'target_field' => $target,
            'transform' => $transform,
            'confidence' => $confidence,
            'options' => [],
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = mb_strtolower(trim($header));
        $normalized = preg_replace('/[_\-\.]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @param  array<int, string>  $samples
     */
    private function guessDateFormat(array $samples): string
    {
        foreach ($samples as $sample) {
            if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $sample)) {
                return 'Y-m-d';
            }
            if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $sample)) {
                return 'd/m/Y';
            }
        }

        return 'd/m/Y';
    }
}
