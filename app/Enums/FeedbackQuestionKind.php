<?php

namespace App\Enums;

enum FeedbackQuestionKind: string
{
    case Text = 'TEXT';
    case Single = 'SINGLE';
    case Multi = 'MULTI';
    case Scale = 'SCALE';
}
