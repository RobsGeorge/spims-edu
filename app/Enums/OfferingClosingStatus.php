<?php

namespace App\Enums;

enum OfferingClosingStatus: string
{
    case Open = 'OPEN';
    case GradingLocked = 'GRADING_LOCKED';
    case Announced = 'ANNOUNCED';
    case Closed = 'CLOSED';

    /**
     * The status machine only ever advances forward: OPEN -> GRADING_LOCKED ->
     * ANNOUNCED -> CLOSED. Returns the single next status, or null once closed.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Open => self::GradingLocked,
            self::GradingLocked => self::Announced,
            self::Announced => self::Closed,
            self::Closed => null,
        };
    }

    public function canAdvanceTo(self $target): bool
    {
        return $this->next() === $target;
    }
}
