<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Submitted    = 'submitted';
    case Graded       = 'graded';
}
