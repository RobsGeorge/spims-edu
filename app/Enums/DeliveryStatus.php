<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Skipped = 'SKIPPED';
    case Failed = 'FAILED';
}
