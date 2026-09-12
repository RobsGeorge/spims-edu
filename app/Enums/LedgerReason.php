<?php

namespace App\Enums;

enum LedgerReason: string
{
    case Topup = 'TOPUP';
    case Refund = 'REFUND';
    case Payment = 'PAYMENT';
    case Donation = 'DONATION';
    case AdminGrant = 'ADMIN_GRANT';
    case Adjustment = 'ADJUSTMENT';

    /**
     * L6 legacy import: a credit balance carried forward from Populi at cutover.
     * See docs/legacy-data-import-plan.md §8 / D4.
     */
    case LegacyCarryForward = 'LEGACY_CARRY_FORWARD';
}
