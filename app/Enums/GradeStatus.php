<?php

namespace App\Enums;

enum GradeStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Submitted = 'SUBMITTED';
    case Locked = 'LOCKED';
}
