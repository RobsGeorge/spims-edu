<?php

namespace App\Enums;

enum FeedbackIdentityRevealStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Denied = 'DENIED';
}
