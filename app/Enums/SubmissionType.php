<?php

namespace App\Enums;

enum SubmissionType: string
{
    case File = 'FILE';
    case Text = 'TEXT';
    case Both = 'BOTH';
}
