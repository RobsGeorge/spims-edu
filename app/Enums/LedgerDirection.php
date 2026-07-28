<?php

namespace App\Enums;

enum LedgerDirection: string
{
    case Credit = 'CREDIT';
    case Debit = 'DEBIT';
}
