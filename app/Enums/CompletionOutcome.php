<?php

namespace App\Enums;

enum CompletionOutcome: string
{
    case Completed = 'COMPLETED';
    case NotCompleted = 'NOT_COMPLETED';
    case Pending = 'PENDING';
}
