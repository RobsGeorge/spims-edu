<?php

namespace App\Enums;

enum SemesterStatus: string
{
    case Draft = 'DRAFT';
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Closed = 'CLOSED';

    /** @return SemesterStatus[] */
    public static function legalNextStates(self $from): array
    {
        return match ($from) {
            self::Draft      => [self::Open],
            self::Open       => [self::InProgress],
            self::InProgress => [self::Closed],
            self::Closed     => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, self::legalNextStates($this), true);
    }
}
