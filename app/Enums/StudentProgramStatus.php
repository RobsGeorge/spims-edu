<?php

namespace App\Enums;

enum StudentProgramStatus: string
{
    case Active = 'ACTIVE';
    case Completed = 'COMPLETED';
    case Withdrawn = 'WITHDRAWN';
}
