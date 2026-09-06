<?php

namespace App\Enums;

enum AnnouncementTargetType: string
{
    case Offering = 'OFFERING';
    case Program = 'PROGRAM';
    case Semester = 'SEMESTER';
    case Role = 'ROLE';
    case User = 'USER';
}
