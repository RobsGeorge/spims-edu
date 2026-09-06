<?php

namespace App\Enums;

enum FeedbackSurveyStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Closed = 'CLOSED';
}
