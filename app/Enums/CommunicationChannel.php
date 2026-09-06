<?php

namespace App\Enums;

enum CommunicationChannel: string
{
    case InApp = 'in_app';
    case Mail = 'mail';
    case Whatsapp = 'whatsapp';
}
