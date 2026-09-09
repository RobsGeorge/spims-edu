<?php

namespace App\Console\Commands;

use App\Services\Help\HelpCatalogOpsService;
use Illuminate\Console\Command;

/**
 * Optional ops backup of Help CMS catalog to JSON (CMS-3).
 */
class HelpExportCommand extends Command
{
    protected $signature = 'help:export
                            {--path= : Output file (default storage/app/help-export/help-YYYYMMDD_His.json)}';

    protected $description = 'Export Help CMS categories and articles to JSON for ops backup.';

    public function handle(HelpCatalogOpsService $ops): int
    {
        $path = $this->option('path');
        if (! is_string($path) || $path === '') {
            $path = storage_path('app/help-export/help-'.now()->format('Ymd_His').'.json');
        }

        $written = $ops->exportToJsonFile($path);
        $payload = $ops->exportToArray();
        $this->info(sprintf(
            'Exported %d categories and %d articles to %s',
            count($payload['categories']),
            count($payload['articles']),
            $written
        ));

        return self::SUCCESS;
    }
}
