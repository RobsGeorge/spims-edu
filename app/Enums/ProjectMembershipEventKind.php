<?php

namespace App\Enums;

enum ProjectMembershipEventKind: string
{
    case Join = 'JOIN';
    case Leave = 'LEAVE';
    case Move = 'MOVE';
    case Merge = 'MERGE';
}
