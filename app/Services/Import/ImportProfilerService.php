<?php

namespace App\Services\Import;

/**
 * Profiles an uploaded file's columns before any mapping decision is made — inferred
 * type, null percentage, distinct count, and sample values. This is what the mapping
 * screen shows the registrar, and what the suggestion engine reads to score matches.
 * See docs/legacy-data-import-plan.md §5.1.
 */
class ImportProfilerService
{
    private const SAMPLE_COUNT = 5;

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|null>>  $rows
     * @return array<int, array{column: string, type: string, empty_percent: float, distinct_count: int, samples: array<int, string>}>
     */
    public function profile(array $headers, array $rows): array
    {
        $total = count($rows);
        $profile = [];

        foreach ($headers as $index => $header) {
            $values = array_map(
                fn (array $row) => isset($row[$index]) ? trim((string) $row[$index]) : '',
                $rows,
            );
            $nonEmpty = array_values(array_filter($values, fn ($v) => $v !== ''));

            $emptyCount = $total - count($nonEmpty);
            $distinct = count(array_unique($nonEmpty));

            $profile[] = [
                'column' => $header,
                'type' => $this->inferType($nonEmpty),
                'empty_percent' => $total > 0 ? round($emptyCount / $total * 100, 1) : 0.0,
                'distinct_count' => $distinct,
                'samples' => array_slice(array_values(array_unique($nonEmpty)), 0, self::SAMPLE_COUNT),
            ];
        }

        return $profile;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function inferType(array $values): string
    {
        if ($values === []) {
            return 'unknown';
        }

        $sample = array_slice($values, 0, 200);
        $count = count($sample);

        $matches = fn (callable $test) => count(array_filter($sample, $test)) / $count >= 0.9;

        if ($matches(fn ($v) => (bool) preg_match('/^-?\d+$/', $v))) {
            return 'integer';
        }
        if ($matches(fn ($v) => (bool) preg_match('/^-?\d+[.,]\d+$/', $v))) {
            return 'decimal';
        }
        if ($matches(fn ($v) => (bool) filter_var($v, FILTER_VALIDATE_EMAIL))) {
            return 'email';
        }
        if ($matches(fn ($v) => $this->looksLikeDate($v))) {
            return 'date';
        }
        if ($matches(fn ($v) => in_array(mb_strtolower($v), ['true', 'false', 'yes', 'no', '1', '0', 'y', 'n'], true))) {
            return 'boolean';
        }

        return 'text';
    }

    private function looksLikeDate(string $value): bool
    {
        return (bool) preg_match('#^\d{1,4}[/-]\d{1,2}[/-]\d{1,4}$#', $value);
    }
}
