<?php

namespace App\Enums;

/**
 * What an import batch's rows describe. Only Student is implemented in v1 — see
 * docs/legacy-data-import-plan.md phases L0-L1. Additional cases (course results,
 * balances, credentials, identity crosswalk) are additive and land with later phases.
 */
enum ImportEntityType: string
{
    case Student = 'STUDENT';
}
