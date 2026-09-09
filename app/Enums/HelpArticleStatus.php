<?php

namespace App\Enums;

enum HelpArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
