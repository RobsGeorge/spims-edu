<?php

namespace App\Enums;

enum CompletionCriterionKind: string
{
    case MinGrade = 'MIN_GRADE';
    case MinAttendance = 'MIN_ATTENDANCE';
    case RequiredItem = 'REQUIRED_ITEM';
    case MinDiscussion = 'MIN_DISCUSSION';
}
