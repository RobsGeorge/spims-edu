<?php

namespace App\Enums;

enum AnnouncementStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
}
