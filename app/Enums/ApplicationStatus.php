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
    case Withdrawn = 'WITHDRAWN';

    /**
     * Queue / in-flight statuses that block a second application for the same program.
     * SUBMITTED is a queue status only; submit() lands on UNDER_REVIEW.
     * WITHDRAWN is a terminal applicant action — start() may open a new case.
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

    public function isWithdrawable(): bool
    {
        return $this->isOpen();
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Waitlisted => 'waitlist',
            self::UnderReview, self::Submitted => 'pending',
            self::Draft, self::Withdrawn => 'neutral',
        };
    }
}
