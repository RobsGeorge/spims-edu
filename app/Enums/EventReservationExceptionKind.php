<?php

namespace App\Enums;

enum EventReservationExceptionKind: string
{
    case Allow = 'ALLOW';
    case Deny = 'DENY';
}
