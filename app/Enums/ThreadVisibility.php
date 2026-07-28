<?php

namespace App\Enums;

enum ThreadVisibility: string
{
    case Open = 'OPEN';
    case PrivateToInstructor = 'PRIVATE_TO_INSTRUCTOR';
}
