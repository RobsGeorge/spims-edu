<?php

namespace App\Enums;

enum LiveQuizSessionState: string
{
    case Lobby = 'LOBBY';
    case QuestionOpen = 'QUESTION_OPEN';
    case QuestionClosed = 'QUESTION_CLOSED';
    case Results = 'RESULTS';
    case Ended = 'ENDED';
}
