<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Submitted = 'SUBMITTED';
    case AutoSubmitted = 'AUTO_SUBMITTED';
    case Graded = 'GRADED';

    /** Ended by ProctorService after crossing the proctoring escalation threshold. */
    case Terminated = 'TERMINATED';
}
