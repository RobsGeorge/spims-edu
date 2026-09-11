<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Pending = 'PENDING';

    /**
     * An imported alumnus with no portal access — no password, never sent mail.
     * See docs/legacy-data-import-plan.md D8.
     */
    case Archived = 'ARCHIVED';
}
