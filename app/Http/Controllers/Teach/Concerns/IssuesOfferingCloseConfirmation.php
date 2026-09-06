<?php

namespace App\Http\Controllers\Teach\Concerns;

use App\Enums\OfferingClosingStatus;
use App\Exceptions\AuthorizationException;
use App\Models\CourseOffering;
use App\Models\User;
use App\Services\Completion\OfferingClosingService;
use App\Support\AuthorizeService;
use App\Support\ConfirmationToken;

trait IssuesOfferingCloseConfirmation
{
    protected function offeringCloseToken(
        User $user,
        CourseOffering $offering,
        AuthorizeService $authorize,
        ConfirmationToken $confirm,
        OfferingClosingService $closing,
    ): ?string {
        try {
            $authorize->authorize($user, 'offering.close', $offering);
        } catch (AuthorizationException) {
            return null;
        }

        if ($closing->statusFor($offering) !== OfferingClosingStatus::Announced) {
            return null;
        }

        return $confirm->issue('offering.close.'.$offering->id);
    }
}
