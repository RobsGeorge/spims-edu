<?php

namespace App\Enums;

/**
 * What an import batch's rows describe. Student landed in L0-L1; CourseResult in L3 —
 * see docs/legacy-data-import-plan.md §7. Additional cases (balances, credentials,
 * identity crosswalk) are additive and land with later phases.
 */
enum ImportEntityType: string
{
    case Student = 'STUDENT';
    case CourseResult = 'COURSE_RESULT';
}
