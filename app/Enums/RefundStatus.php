<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Completed = 'COMPLETED';
}
