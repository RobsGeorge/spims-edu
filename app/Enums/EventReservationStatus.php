<?php

namespace App\Enums;

enum EventReservationStatus: string
{
    case Reserved = 'RESERVED';
    case Waitlisted = 'WAITLISTED';
    case Cancelled = 'CANCELLED';
}
