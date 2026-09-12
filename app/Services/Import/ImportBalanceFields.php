<?php

namespace App\Services\Import;

/**
 * The target field catalog for a BALANCE batch (L6 — finance opening balances). Kept
 * as one source of truth so the mapping screen's guard, the validator, and the
 * committer can never disagree about what "required" means — same convention as
 * ImportStudentFields. See docs/legacy-data-import-plan.md §8, D4, §14 Q11/Q13/Q15.
 */
class ImportBalanceFields
{
    /**
     * The only two currencies the Currency enum knows. Per plan §14 Q14, the importer
     * "will not invent an FX rate" — anything else is a hard validation error naming
     * the unsupported currency.
     *
     * @var array<int, string>
     */
    public const SUPPORTED_CURRENCIES = ['EGP', 'USD'];

    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            'legacy_id' => 'Legacy id (the student)',
            'currency' => 'Currency (EGP or USD)',
            'owed_minor' => 'Amount owed (creates an opening invoice)',
            'credit_minor' => 'Amount in hand (creates a wallet credit)',
        ];
    }

    /**
     * legacy_id and currency must be produced by at least one mapping. owed_minor and
     * credit_minor are individually optional — a row with neither is a no-op, not an
     * error (§8: "no empty invoices") — but at least one of the two money fields must
     * be mapped somewhere in the file for the batch to do anything at all; that check
     * lives in validation, not here, since it is file-wide rather than per-column.
     *
     * @return array<int, string>
     */
    public static function alwaysRequired(): array
    {
        return ['legacy_id', 'currency'];
    }

    /**
     * BALANCE has no population split (D4 carries no ALUMNI/ACTIVE distinction) — the
     * signature is kept identical to ImportStudentFields::requiredFor() so the
     * controller's dispatch can call either catalog the same way.
     *
     * @return array<int, string>
     */
    public static function requiredFor(?\App\Enums\ImportPopulation $population = null): array
    {
        return self::alwaysRequired();
    }
}
