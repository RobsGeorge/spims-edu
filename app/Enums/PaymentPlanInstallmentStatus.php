<?php

namespace App\Enums;

enum PaymentPlanInstallmentStatus: string
{
    case Pending = 'PENDING';
    case Due = 'DUE';
    case Overdue = 'OVERDUE';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';
}
