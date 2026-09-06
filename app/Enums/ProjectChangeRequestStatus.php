<?php

namespace App\Enums;

enum ProjectChangeRequestStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
