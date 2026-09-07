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

    /**
     * Queue / in-flight statuses that block a second application for the same program.
     * SUBMITTED is a queue status only; submit() lands on UNDER_REVIEW.
     *
     * @return list<self>
     */
    public static function openCases(): array
    {
        return [
            self::Draft,
            self::Submitted,
            self::UnderReview,
            self::Waitlisted,
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openCases(), true);
    }
}
