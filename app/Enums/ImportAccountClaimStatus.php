<?php

namespace App\Enums;

/**
 * The account-claim cohort for currently-studying legacy students — see
 * docs/legacy-data-import-plan.md §9. Tracks one imported user from "will be invited"
 * through "logged in", entirely separate from the batch/row lifecycle in
 * ImportBatchStatus/ImportRowStatus.
 */
enum ImportAccountClaimStatus: string
{
    case Queued = 'QUEUED';
    case Sent = 'SENT';
    case Claimed = 'CLAIMED';
    case Bounced = 'BOUNCED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';
}
