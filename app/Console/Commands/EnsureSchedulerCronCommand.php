<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnsureSchedulerCronCommand extends Command
{
    protected $signature = 'scheduler:ensure-cron {--php=php} {--apply : Write the crontab entry if missing}';

    protected $description = 'Ensure the Laravel scheduler cron entry exists (idempotent check).';

    public function handle(): int
    {
        $php = $this->option('php');
        $path = base_path();
        $line = "* * * * * cd {$path} && {$php} artisan schedule:run >> /dev/null 2>&1";

        if (! $this->option('apply')) {
            $this->info('Add this cron entry for the deploy user if not present:');
            $this->line($line);

            return self::SUCCESS;
        }

        $existing = $this->readCrontab();
        if (str_contains($existing, 'artisan schedule:run') && str_contains($existing, $path)) {
            $this->info('Scheduler cron already present.');

            return self::SUCCESS;
        }

        $prefix = rtrim($existing);
        $new = ($prefix === '' ? '' : $prefix.PHP_EOL).$line.PHP_EOL;

        if (! $this->writeCrontab($new)) {
            $this->warn('Could not write crontab; add this line manually:');
            $this->line($line);

            return self::SUCCESS;
        }

        $this->info('Scheduler cron installed.');

        return self::SUCCESS;
    }

    private function readCrontab(): string
    {
        $output = [];
        $code = 0;
        exec('crontab -l 2>/dev/null', $output, $code);

        return $code === 0 ? implode(PHP_EOL, $output) : '';
    }

    private function writeCrontab(string $contents): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'spims-cron-');
        if ($tmp === false) {
            return false;
        }

        file_put_contents($tmp, $contents);
        $code = 0;
        exec('crontab '.escapeshellarg($tmp).' 2>/dev/null', $out, $code);
        @unlink($tmp);

        return $code === 0;
    }
}
