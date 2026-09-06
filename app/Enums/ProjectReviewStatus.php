<?php

namespace App\Enums;

enum ProjectReviewStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case NeedsRevision = 'NEEDS_REVISION';
}
