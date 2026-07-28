<?php

namespace App\Enums;

enum QuestionType: string
{
    case McqSingle = 'MCQ_SINGLE';
    case McqMulti = 'MCQ_MULTI';
    case TrueFalse = 'TRUE_FALSE';
    case ShortAnswer = 'SHORT_ANSWER';
    case Essay = 'ESSAY';
    case Matching = 'MATCHING';
    case FillBlank = 'FILL_BLANK';
    case Numeric = 'NUMERIC';
    case Ordering = 'ORDERING';
    case FileUpload = 'FILE_UPLOAD';
}
