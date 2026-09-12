<?php

namespace App\Enums;

/**
 * What an import batch's rows describe. Additional cases land with later phases; see
 * docs/legacy-data-import-plan.md §13.
 *
 * - Student: L0/L1, identity + program linkage.
 * - Balance: L6, finance opening balances (one carried-forward invoice or wallet
 *   credit per student per currency, gated by an exact control-total match — §8).
 */
enum ImportEntityType: string
{
    case Student = 'STUDENT';
    case Balance = 'BALANCE';
}
