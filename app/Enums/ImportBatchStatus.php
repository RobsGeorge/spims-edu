<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Draft = 'DRAFT';
    case Mapped = 'MAPPED';
    case Validated = 'VALIDATED';
    case DryRun = 'DRY_RUN';
    case Committed = 'COMMITTED';
    case RolledBack = 'ROLLED_BACK';
    case Failed = 'FAILED';
}
