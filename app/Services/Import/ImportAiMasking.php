<?php

namespace App\Services\Import;

/**
 * L8, Part B — masking policy for anything that reaches the AI provider. See
 * docs/legacy-data-import-plan.md §22.3.
 *
 * The masking rule here is deliberately stronger than "redact this particular value":
 * every masked sample is a **fixed, canonical placeholder chosen from the column's
 * inferred type alone** — `samplesForType()` never receives, reads, or touches a real
 * value from the sheet at all. That makes "no raw value can leave" true by
 * construction rather than by a redaction routine that could have a bug — there is
 * nothing to redact, because nothing real is ever passed in. `ImportAiMappingTest`
 * proves this directly: it builds a profile carrying real-looking PII in its sample
 * arrays and asserts the built payload contains none of it, for any column type.
 */
class ImportAiMasking
{
    /**
     * @return array<int, string>
     */
    public static function samplesForType(string $type): array
    {
        return match ($type) {
            'email' => ['a***@g***.com', 'm***@y***.net'],
            'date' => ['19XX-XX-XX', 'XX/XX/XXXX'],
            'integer' => ['#####', '######'],
            'decimal' => ['3.4_', '0._'],
            'phone' => ['+2XX XXX XXXX'],
            'boolean' => ['true/false'],
            default => ['Xxxx Xxxx', 'Xxxxxxxx'],
        };
    }
}
