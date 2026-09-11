<?php

namespace App\Enums;

enum ImportSourceKind: string
{
    case Sis = 'SIS';
    case Lms = 'LMS';
}
