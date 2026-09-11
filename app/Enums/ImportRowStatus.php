<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case Pending = 'PENDING';
    case Valid = 'VALID';
    case Warn = 'WARN';
    case Error = 'ERROR';
    case Applied = 'APPLIED';
    case RolledBack = 'ROLLED_BACK';
}
