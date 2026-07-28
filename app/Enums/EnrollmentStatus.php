<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Enrolled = 'ENROLLED';
    case Waitlisted = 'WAITLISTED';
    case Dropped = 'DROPPED';
    case Withdrawn = 'WITHDRAWN';
    case Completed = 'COMPLETED';
}
