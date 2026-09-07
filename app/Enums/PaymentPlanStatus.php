<?php

namespace App\Enums;

enum PaymentPlanStatus: string
{
    case Open = 'OPEN';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
