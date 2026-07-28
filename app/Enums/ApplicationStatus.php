<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case UnderReview = 'UNDER_REVIEW';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Waitlisted = 'WAITLISTED';
}
