<?php

namespace App\Enums;

enum ContentItemType: string
{
    case Video = 'VIDEO';
    case Reading = 'READING';
    case Text = 'TEXT';
    case File = 'FILE';
    case Assignment = 'ASSIGNMENT';
    case Quiz = 'QUIZ';
    case Exam = 'EXAM';
    case Discussion = 'DISCUSSION';
}
