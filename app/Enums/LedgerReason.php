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
}
