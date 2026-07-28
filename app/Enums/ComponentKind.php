<?php

namespace App\Enums;

enum ComponentKind: string
{
    case Assignment = 'ASSIGNMENT';
    case Quiz = 'QUIZ';
    case Exam = 'EXAM';
    case Attendance = 'ATTENDANCE';
    case Discussion = 'DISCUSSION';
    case Other = 'OTHER';
}
