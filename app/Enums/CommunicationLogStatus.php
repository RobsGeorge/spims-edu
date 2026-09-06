<?php

namespace App\Enums;

enum CommunicationLogStatus: string
{
    case Sent = 'SENT';
    case Skipped = 'SKIPPED';
    case Failed = 'FAILED';
}
