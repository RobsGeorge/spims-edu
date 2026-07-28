<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case PendingVerification = 'PENDING_VERIFICATION';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Refunded = 'REFUNDED';
}
