<?php

namespace App\Enums;

enum ClassSessionMode: string
{
    case InPerson = 'IN_PERSON';
    case Online = 'ONLINE';
    case Hybrid = 'HYBRID';
}
