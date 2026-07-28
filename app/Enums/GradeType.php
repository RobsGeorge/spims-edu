<?php

namespace App\Enums;

enum GradeType: string
{
    case Standard = 'STANDARD';
    case Withdrawal = 'WITHDRAWAL';
    case PassFail = 'PASS_FAIL';
    case Audit = 'AUDIT';
    case InProgress = 'IN_PROGRESS';
}
