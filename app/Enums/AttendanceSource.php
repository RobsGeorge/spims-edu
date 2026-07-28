<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case ZoomImport = 'ZOOM_IMPORT';
    case Manual = 'MANUAL';
}
