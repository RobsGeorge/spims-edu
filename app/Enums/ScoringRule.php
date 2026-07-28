<?php

namespace App\Enums;

enum ScoringRule: string
{
    case Highest = 'HIGHEST';
    case Latest = 'LATEST';
    case Average = 'AVERAGE';
}
