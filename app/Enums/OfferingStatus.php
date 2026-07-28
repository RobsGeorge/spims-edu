<?php

namespace App\Enums;

enum OfferingStatus: string
{
    case Draft = 'DRAFT';
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Archived = 'ARCHIVED';
}
