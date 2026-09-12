<?php

namespace App\Enums;

enum ImportRowAction: string
{
    case Create = 'CREATE';
    case Update = 'UPDATE';
    case Link = 'LINK';
    case Noop = 'NOOP';

    /**
     * Rung 6 of the matching ladder: the row reached the end of the ladder without a
     * deterministic match and is now an `import_merge_candidates` row awaiting a human
     * decision. Not yet APPLIED — see docs/legacy-data-import-plan.md §6.
     */
    case Queued = 'QUEUED';
}
