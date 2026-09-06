<?php

namespace App\Enums;

enum ProjectAssessmentStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Locked = 'LOCKED';
}
