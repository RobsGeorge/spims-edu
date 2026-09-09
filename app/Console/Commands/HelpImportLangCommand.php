<?php

namespace App\Console\Commands;

use App\Services\Help\HelpCatalogOpsService;
use Illuminate\Console\Command;

/**
 * One-shot / idempotent Help CMS import (CMS-3).
 *
 * Priority: --path JSON → legacy lang/{locale}/help.php articles → HelpSeeder catalog.
 * UI chrome keys in lang/{locale}/help.php are never removed by this command.
 */
class HelpImportLangCommand extends Command
{
    protected $signature = 'help:import-lang
                            {--path= : JSON catalog from help:export (or compatible)}
                            {--seed : Force HelpSeeder even when lang articles exist}';

    protected $description = 'Import help articles into the CMS DB (idempotent upsert by slug).';

    public function handle(HelpCatalogOpsService $ops): int
    {
        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            try {
                $counts = $ops->importFromJsonFile($path);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info(sprintf(
                'Imported from JSON [%s]: %d categories, %d articles, %d locales.',
                $path,
                $counts['categories'],
                $counts['articles'],
                $counts['locales']
            ));

            return self::SUCCESS;
        }

        if (! $this->option('seed')) {
            $langCounts = $ops->importFromLangFiles();
            if ($langCounts !== null) {
                $this->info(sprintf(
                    'Imported from lang help.php articles: %d categories, %d articles, %d locales.',
                    $langCounts['categories'],
                    $langCounts['articles'],
                    $langCounts['locales']
                ));
                $this->comment('UI chrome keys in lang help.php were left unchanged.');

                return self::SUCCESS;
            }
        }

        $counts = $ops->importFromSeeder();
        $this->info(sprintf(
            'Imported from documented seed path [%s]: %d categories, %d articles, %d locales.',
            $counts['source'],
            $counts['categories'],
            $counts['articles'],
            $counts['locales']
        ));
        $this->comment('No lang article bodies found (or --seed). Re-ran HelpSeeder upsert by slug.');
        $this->comment('UI chrome remains in lang/{en,ar,fr}/help.php.');

        return self::SUCCESS;
    }
}
