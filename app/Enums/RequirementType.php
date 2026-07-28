<?php

namespace App\Enums;

enum RequirementType: string
{
    case Required = 'REQUIRED';
    case Elective = 'ELECTIVE';
}
