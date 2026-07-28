<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'spims:backup-database
                            {--path= : Override backup directory}
                            {--keep= : Retention days override}';

    protected $description = 'Create a database dump and prune old backups.';

    public function handle(): int
    {
        $dir = $this->option('path') ?: config('spims.backup.path');
        $keep = (int) ($this->option('keep') ?: config('spims.backup.retention_days'));

        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd_His');
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? '') === 'pgsql') {
            $file = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."spims_{$stamp}.sql.gz";
            $env = array_merge($_ENV, [
                'PGPASSWORD' => (string) ($config['password'] ?? ''),
            ]);
            $cmd = sprintf(
                'pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl | gzip -c > %s',
                escapeshellarg((string) $config['host']),
                escapeshellarg((string) ($config['port'] ?? '5432')),
                escapeshellarg((string) $config['username']),
                escapeshellarg((string) $config['database']),
                escapeshellarg($file)
            );
            $process = Process::fromShellCommandline($cmd, null, $env, null, 600);
            $process->run();
            if (! $process->isSuccessful()) {
                $this->error('pg_dump failed: '.$process->getErrorOutput());

                return self::FAILURE;
            }
        } elseif (($config['driver'] ?? '') === 'sqlite') {
            $database = $config['database'] ?? '';
            if ($database === ':memory:' || $database === '') {
                $file = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."spims_{$stamp}.sqlite.marker";
                File::put($file, 'in-memory sqlite — no dump produced');
            } else {
                $file = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."spims_{$stamp}.sqlite";
                if (! File::copy($database, $file)) {
                    $this->error('Failed to copy sqlite database.');

                    return self::FAILURE;
                }
            }
        } else {
            $this->error("Unsupported driver [{$config['driver']}] for backups.");

            return self::FAILURE;
        }

        $this->info("Backup written: {$file}");
        $this->prune($dir, $keep);

        return self::SUCCESS;
    }

    private function prune(string $dir, int $keepDays): void
    {
        if ($keepDays < 1) {
            return;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < $cutoff && str_starts_with($file->getFilename(), 'spims_')) {
                File::delete($file->getPathname());
                $this->line('Pruned: '.$file->getFilename());
            }
        }
    }
}
