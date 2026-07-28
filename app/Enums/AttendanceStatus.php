<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'PRESENT';
    case Absent = 'ABSENT';
}
