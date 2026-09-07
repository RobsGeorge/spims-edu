<?php

namespace App\Console\Commands;

use App\Services\SuperAdmin\AuditExplorerService;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    protected $signature = 'spims:prune-audit-logs {--days= : Override retention days}';

    protected $description = 'Delete ordinary audit_logs older than retention. Control-plane actions use a 3× floor.';

    public function handle(AuditExplorerService $explorer): int
    {
        $days = $this->option('days');
        $result = $explorer->prune($days !== null && $days !== '' ? (int) $days : null);

        $this->info(sprintf(
            'Pruned %d audit row(s). Kept %d protected control-plane row(s) inside the 3× floor. Retention: %d day(s).',
            $result['deleted'],
            $result['kept_protected'],
            $result['retention_days']
        ));

        return self::SUCCESS;
    }
}
