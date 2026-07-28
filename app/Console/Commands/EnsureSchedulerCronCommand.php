<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnsureSchedulerCronCommand extends Command
{
    protected $signature = 'scheduler:ensure-cron {--php=php}';

    protected $description = 'Ensure the Laravel scheduler cron entry exists (idempotent check).';

    public function handle(): int
    {
        $php = $this->option('php');
        $path = base_path();
        $line = "* * * * * cd {$path} && {$php} artisan schedule:run >> /dev/null 2>&1";

        $this->info('Add this cron entry for the deploy user if not present:');
        $this->line($line);

        return self::SUCCESS;
    }
}
