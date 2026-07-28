<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Submitted = 'SUBMITTED';
    case AutoSubmitted = 'AUTO_SUBMITTED';
    case Graded = 'GRADED';
}
