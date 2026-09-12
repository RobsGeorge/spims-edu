<?php

namespace App\Services\Import;

use Carbon\Carbon;

/**
 * Composable, previewable transforms applied to a mapped column's raw value. Every
 * transform is deterministic and pure — same input, same output — so the mapping
 * screen's live preview and the real validate/commit pass never disagree.
 * See docs/legacy-data-import-plan.md §5.3.
 */
class ImportTransformService
{
    /**
     * @return array<string, string>
     */
    public function available(): array
    {
        return [
            'none' => 'None (use value as-is)',
            'trim' => 'Trim whitespace',
            'lower' => 'lowercase',
            'upper' => 'UPPERCASE',
            'date' => 'Parse date',
            'name_part_first' => 'Extract first name from a full-name column',
            'name_part_last' => 'Extract last name from a full-name column',
            'normalize_arabic' => 'Normalize Arabic (strip tashkeel)',
            'constant' => 'Fixed value for every row',
            'money_to_minor' => 'Parse money (decimal string -> integer minor units)',
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{value: mixed, ok: bool, note: ?string, code?: string}
     */
    public function apply(string $transform, ?string $raw, array $options = []): array
    {
        $value = $raw === null ? null : trim($raw);

        return match ($transform) {
            'none' => ['value' => $value, 'ok' => true, 'note' => null],
            'trim' => ['value' => $value, 'ok' => true, 'note' => null],
            'lower' => ['value' => $value !== null ? mb_strtolower($value) : null, 'ok' => true, 'note' => null],
            'upper' => ['value' => $value !== null ? mb_strtoupper($value) : null, 'ok' => true, 'note' => null],
            'date' => $this->parseDate($value, (string) ($options['format'] ?? 'd/m/Y')),
            'name_part_first' => $this->namePart($value, (string) ($options['format'] ?? 'last_first'), 'first'),
            'name_part_last' => $this->namePart($value, (string) ($options['format'] ?? 'last_first'), 'last'),
            'normalize_arabic' => ['value' => $value !== null ? $this->normalizeArabic($value) : null, 'ok' => true, 'note' => null],
            'constant' => ['value' => isset($options['value']) ? (string) $options['value'] : null, 'ok' => true, 'note' => null],
            'money_to_minor' => $this->parseMoneyToMinor($value),
            default => ['value' => $value, 'ok' => true, 'note' => null],
        };
    }

    /**
     * Parses a decimal **string** into an integer minor-unit amount by integer
     * arithmetic only — never a float cast (hard rule 3). Thousands separators
     * (commas) are stripped before parsing. More than two decimal places in the
     * source value is a hard error (`E_MONEY_PRECISION`) — rejected, never rounded.
     * See docs/legacy-data-import-plan.md §5.3.
     *
     * @return array{value: ?int, ok: bool, note: ?string, code?: string}
     */
    public function parseMoneyToMinor(?string $value): array
    {
        if ($value === null || $value === '') {
            return ['value' => null, 'ok' => true, 'note' => null];
        }

        // Thousands separators only — this is not a locale-aware parser. "1,234.50".
        $stripped = str_replace(',', '', $value);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $stripped, $matches)) {
            return [
                'value' => null,
                'ok' => false,
                'note' => "\"{$value}\" is not a valid amount.",
                'code' => 'E_BAD_MONEY',
            ];
        }

        [, $sign, $intPart, $fracPart] = $matches + [3 => ''];

        if (strlen($fracPart) > 2) {
            return [
                'value' => null,
                'ok' => false,
                'note' => "\"{$value}\" has more than two decimal places.",
                'code' => 'E_MONEY_PRECISION',
            ];
        }

        $fracMinor = (int) str_pad($fracPart, 2, '0', STR_PAD_RIGHT);
        $minor = ((int) $intPart) * 100 + $fracMinor;

        if ($sign === '-') {
            $minor = -$minor;
        }

        return ['value' => $minor, 'ok' => true, 'note' => null];
    }

    /**
     * @return array{value: ?string, ok: bool, note: ?string}
     */
    private function parseDate(?string $value, string $format): array
    {
        if ($value === null || $value === '') {
            return ['value' => null, 'ok' => true, 'note' => null];
        }

        try {
            $date = Carbon::createFromFormat($format, $value);
            // Carbon/DateTime silently rolls an out-of-range component over into the
            // next unit (month 14 becomes February of the following year) instead of
            // failing. Reformatting the parsed date and comparing it back to the input
            // is what actually catches that — a genuine round trip, not just "did this
            // not throw".
            if ($date === false || $date->format($format) !== $value) {
                return ['value' => null, 'ok' => false, 'note' => "Did not match format {$format}"];
            }

            return ['value' => $date->format('Y-m-d'), 'ok' => true, 'note' => null];
        } catch (\Throwable) {
            return ['value' => null, 'ok' => false, 'note' => "Did not match format {$format}"];
        }
    }

    /**
     * Splits a full name. "last_first" expects "Last, First" or "Last First" order;
     * "first_last" expects "First Last". A comma is treated as authoritative when
     * present, since that is how Populi exports "Last, First".
     *
     * @return array{value: ?string, ok: bool, note: ?string}
     */
    private function namePart(?string $value, string $format, string $part): array
    {
        if ($value === null || $value === '') {
            return ['value' => null, 'ok' => true, 'note' => null];
        }

        if (str_contains($value, ',')) {
            [$last, $first] = array_pad(array_map('trim', explode(',', $value, 2)), 2, '');
        } else {
            $tokens = preg_split('/\s+/u', trim($value)) ?: [];
            if ($format === 'first_last') {
                $first = $tokens[0] ?? '';
                $last = implode(' ', array_slice($tokens, 1)) ?: ($tokens[0] ?? '');
            } else {
                $last = $tokens[0] ?? '';
                $first = implode(' ', array_slice($tokens, 1)) ?: ($tokens[0] ?? '');
            }
        }

        $result = $part === 'first' ? $first : $last;

        return ['value' => $result !== '' ? $result : null, 'ok' => $result !== '', 'note' => $result === '' ? 'Could not split name' : null];
    }

    /**
     * Strips Arabic diacritics (tashkeel) and normalizes alef/ta-marbuta variants for
     * matching purposes. Never transliterates — that would invent matches that are not
     * there. See docs/legacy-data-import-plan.md §6.
     */
    public function normalizeArabic(string $value): string
    {
        // Tashkeel + tatweel.
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        // Alef variants -> bare alef.
        $value = preg_replace('/[\x{0622}\x{0623}\x{0625}]/u', "\u{0627}", $value) ?? $value;
        // Ta-marbuta -> ha.
        $value = str_replace("\u{0629}", "\u{0647}", $value);
        // Alef maqsura -> ya.
        $value = str_replace("\u{0649}", "\u{064A}", $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
