<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Open = 'OPEN';
    case Partial = 'PARTIAL';
    case Paid = 'PAID';
    case Void = 'VOID';
    case Refunded = 'REFUNDED';
}
