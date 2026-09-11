<?php

namespace App\Enums;

enum ImportRowAction: string
{
    case Create = 'CREATE';
    case Update = 'UPDATE';
    case Link = 'LINK';
    case Noop = 'NOOP';
}
