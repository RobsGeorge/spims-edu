<?php

namespace App\Enums;

enum ResultsVisibility: string
{
    case Immediate = 'IMMEDIATE';
    case AfterClose = 'AFTER_CLOSE';
    case OnRelease = 'ON_RELEASE';
}
