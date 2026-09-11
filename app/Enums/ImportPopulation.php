<?php

namespace App\Enums;

/**
 * Which of the two migrations a STUDENT batch belongs to — see
 * docs/legacy-data-import-plan.md §3. This is the decision that controls whether a
 * row becomes a login-capable account.
 */
enum ImportPopulation: string
{
    case Alumni = 'ALUMNI';
    case Active = 'ACTIVE';
}
