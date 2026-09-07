<?php

namespace App\Services\SuperAdmin;

use App\Console\Kernel as ConsoleKernel;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Throwable;

class OpsDeskService
{
    public const FAILED_JOB_CAP = 50;

    /** @var list<array{summary: string, command: string, expression: string, human: string, schedule: string, next: ?string}>|null */
    private ?array $scheduleMemo = null;

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function authorizeJobs(User $actor): void
    {
        $this->authorize->authorize($actor, 'ops.failed_jobs');
    }

    public function authorizeBackup(User $actor): void
    {
        $this->authorize->authorize($actor, 'ops.backup');
    }

    /**
     * @return array{
     *     jobs: list<array{uuid: string, queue: string, connection: string, name: string, attempts: int, failed_at: ?string, exception: string}>,
     *     failed_count: int,
     *     pending_count: int,
     *     truncated: bool
     * }
     */
    public function failedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [
                'jobs' => [],
                'failed_count' => 0,
                'pending_count' => Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0,
                'truncated' => false,
            ];
        }

        $failedCount = (int) DB::table('failed_jobs')->count();
        $rows = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(self::FAILED_JOB_CAP)
            ->get();

        $jobs = [];
        foreach ($rows as $row) {
            $jobs[] = [
                'uuid' => (string) $row->uuid,
                'queue' => (string) $row->queue,
                'connection' => (string) $row->connection,
                'name' => $this->jobName((string) $row->payload),
                'attempts' => $this->payloadAttempts((string) $row->payload),
                'failed_at' => $row->failed_at ? (string) $row->failed_at : null,
                'exception' => $this->exceptionPreview((string) $row->exception),
            ];
        }

        return [
            'jobs' => $jobs,
            'failed_count' => $failedCount,
            'pending_count' => Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0,
            'truncated' => $failedCount > self::FAILED_JOB_CAP,
        ];
    }

    public function retry(User $actor, string $uuid): int
    {
        $this->authorizeJobs($actor);
        $row = $this->findFailed($uuid);

        $attempts = $this->payloadAttempts((string) $row->payload) + 1;
        $payload = $this->payloadWithAttempts((string) $row->payload, $attempts);

        $this->audit->withAudit($actor, 'ops.failed_jobs.retry', function () use ($row, $uuid, $payload, $attempts) {
            DB::table('failed_jobs')->where('uuid', $uuid)->delete();
            $connection = (string) $row->connection;
            if ($connection !== '' && $connection !== 'sync') {
                try {
                    Queue::connection($connection)->pushRaw($payload, (string) $row->queue);
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return [
                'uuid' => $uuid,
                'queue' => $row->queue,
                'attempts' => $attempts,
            ];
        }, 'FailedJob');

        return $attempts;
    }

    public function delete(User $actor, string $uuid): void
    {
        $this->authorizeJobs($actor);
        $this->findFailed($uuid);

        $this->audit->withAudit($actor, 'ops.failed_jobs.delete', function () use ($uuid) {
            DB::table('failed_jobs')->where('uuid', $uuid)->delete();

            return ['uuid' => $uuid];
        }, 'FailedJob');
    }

    /**
     * @return array{queued: bool, driver: string, exit: ?int, last_backup_at: ?string}
     */
    public function backupNow(User $actor): array
    {
        $this->authorizeBackup($actor);
        $driver = (string) config('queue.default');
        $queued = $driver !== 'sync';

        return $this->audit->withAudit($actor, 'ops.backup.run', function () use ($queued, $driver) {
            $exit = null;
            if ($queued) {
                Artisan::queue('spims:backup-database');
            } else {
                $exit = Artisan::call('spims:backup-database');
            }

            $snapshot = $this->backupSnapshot();

            return [
                'queued' => $queued,
                'driver' => $driver,
                'exit' => $exit,
                'last_backup_at' => $snapshot['last_backup_at'],
                'path' => $snapshot['path'],
            ];
        }, 'Backup');
    }

    /**
     * @return array{path: string, retention_days: int, last_backup_at: ?string, last_backup_name: ?string}
     */
    public function backupSnapshot(): array
    {
        $path = (string) config('spims.backup.path', storage_path('app/backups'));
        $retention = (int) config('spims.backup.retention_days', 14);
        $lastAt = null;
        $lastName = null;
        if (is_dir($path)) {
            $mtime = null;
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $full = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
                if (! is_file($full)) {
                    continue;
                }
                $fileMtime = filemtime($full);
                if ($fileMtime !== false && ($mtime === null || $fileMtime > $mtime)) {
                    $mtime = $fileMtime;
                    $lastName = $name;
                }
            }
            if ($mtime !== null) {
                $lastAt = date('c', $mtime);
            }
        }

        return [
            'path' => $path,
            'retention_days' => $retention,
            'last_backup_at' => $lastAt,
            'last_backup_name' => $lastName,
        ];
    }

    /**
     * @return list<array{summary: string, command: string, expression: string, human: string, schedule: string, next: ?string}>
     */
    public function scheduledEvents(): array
    {
        if ($this->scheduleMemo !== null) {
            return $this->scheduleMemo;
        }

        // Fresh Schedule so we do not append onto the container singleton twice.
        $schedule = new Schedule;
        $kernel = app(ConsoleKernel::class);
        $method = (new ReflectionClass($kernel))->getMethod('schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $rows = [];
        foreach ($schedule->events() as $event) {
            $summary = $event->getSummaryForDisplay();
            $command = $this->commandName($event, $summary);
            $next = null;
            try {
                $next = $event->nextRunDate(Carbon::now())->toDateTimeString();
            } catch (Throwable) {
                $next = null;
            }
            $human = $this->humanExpression($event->expression);
            $rows[] = [
                'summary' => $summary,
                'command' => $command,
                'expression' => $event->expression,
                'human' => $human,
                'schedule' => $human,
                'next' => $next,
            ];
        }

        return $this->scheduleMemo = $rows;
    }

    /**
     * @return array{driver: string, can_flush: bool, session_count: ?int}
     */
    public function sessionInfo(): array
    {
        $driver = (string) config('session.driver');
        $canFlush = $driver === 'database' && Schema::hasTable('sessions');
        $count = null;
        if ($canFlush) {
            $count = (int) DB::table('sessions')->count();
        }

        return [
            'driver' => $driver,
            'can_flush' => $canFlush,
            'session_count' => $count,
        ];
    }

    /**
     * @return object{uuid: string, queue: string, connection: string, payload: string, exception: string, failed_at: mixed}
     */
    private function findFailed(string $uuid): object
    {
        if (! Schema::hasTable('failed_jobs')) {
            abort(404);
        }

        $row = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        abort_if($row === null, 404);

        return $row;
    }

    private function jobName(string $payload): string
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return __('ops.job_unknown');
        }
        foreach (['displayName', 'data.commandName'] as $key) {
            if ($key === 'displayName' && ! empty($data['displayName'])) {
                return (string) $data['displayName'];
            }
        }
        if (is_array($data['data'] ?? null) && ! empty($data['data']['commandName'])) {
            return (string) $data['data']['commandName'];
        }

        return __('ops.job_unknown');
    }

    private function payloadAttempts(string $payload): int
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return 0;
        }

        return (int) ($data['attempts'] ?? 0);
    }

    private function payloadWithAttempts(string $payload, int $attempts): string
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return $payload;
        }
        $data['attempts'] = $attempts;

        return json_encode($data) ?: $payload;
    }

    private function exceptionPreview(string $exception): string
    {
        $line = trim(strtok(str_replace("\r", "\n", $exception), "\n") ?: $exception);
        if (strlen($line) > 240) {
            return substr($line, 0, 237).'…';
        }

        return $line;
    }

    private function commandName(Event $event, string $summary): string
    {
        if (preg_match("/artisan['\"]?\s+'?([a-z0-9:_-]+)/i", $summary, $m)) {
            return $m[1];
        }
        if (is_string($event->command) && preg_match("/artisan['\"]?\s+'?([a-z0-9:_-]+)/i", $event->command, $m)) {
            return $m[1];
        }

        return $summary;
    }

    private function humanExpression(string $expression): string
    {
        return match ($expression) {
            '* * * * *' => __('ops.schedule_every_minute'),
            '*/5 * * * *' => __('ops.schedule_every_five'),
            '0 0 * * *' => __('ops.schedule_daily_midnight'),
            '30 2 * * *' => __('ops.schedule_daily_0230'),
            '15 3 * * *' => __('ops.schedule_daily_0315'),
            default => $expression,
        };
    }
}
