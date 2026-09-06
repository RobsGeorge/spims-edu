<?php

namespace App\Enums;

enum ProjectDeliverableKind: string
{
    case File = 'FILE';
    case Link = 'LINK';
    case Text = 'TEXT';
}
