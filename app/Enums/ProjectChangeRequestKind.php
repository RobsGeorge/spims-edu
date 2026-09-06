<?php

namespace App\Enums;

enum ProjectChangeRequestKind: string
{
    case Join = 'JOIN';
    case Leave = 'LEAVE';
    case Move = 'MOVE';
    case Merge = 'MERGE';
}
