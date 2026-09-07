<?php

namespace App\Services\SuperAdmin;

use App\Enums\FeedbackIdentityRevealStatus;
use App\Models\FeedbackIdentityRevealRequest;
use App\Models\User;
use App\Support\AuthorizeService;
use App\Support\HealthProbe;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PlatformStatusService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly HealthProbe $health,
        private readonly SystemSettingService $settings,
        private readonly OpsDeskService $ops,
    ) {}

    public function authorize(User $actor): void
    {
        $this->authorize->authorize($actor, 'status.platform');
    }

    /**
     * @return array{
     *     health: array{status: string, ok: bool, checks: array{app: bool, database: bool, cache: bool}, timestamp: string},
     *     integrations: list<array{id: string, configured: bool}>,
     *     runtime: array<string, string>,
     *     backup: array{path: string, retention_days: int, last_backup_at: ?string, last_backup_name: ?string},
     *     jobs: array{failed_count: int, pending_count: int},
     *     reveals: array{available: bool, pending: int, total: int},
     *     demo: array{routed: bool, url: ?string}
     * }
     */
    public function snapshot(): array
    {
        $failed = $this->ops->failedJobs();

        return [
            'health' => $this->health->snapshot(),
            'integrations' => $this->settings->integrations(),
            'runtime' => [
                'queue' => (string) config('queue.default'),
                'session' => (string) config('session.driver'),
                'cache' => (string) config('cache.default'),
                'mailer' => (string) config('mail.default'),
                'filesystem' => (string) config('filesystems.default'),
                'timezone' => (string) config('app.timezone'),
            ],
            'backup' => $this->ops->backupSnapshot(),
            'jobs' => [
                'failed_count' => $failed['failed_count'],
                'pending_count' => $failed['pending_count'],
            ],
            'reveals' => $this->revealCounts(),
            'demo' => $this->demoInfo(),
        ];
    }

    /**
     * @return array{available: bool, pending: int, total: int}
     */
    private function revealCounts(): array
    {
        if (! Schema::hasTable('feedback_identity_reveal_requests')) {
            return ['available' => false, 'pending' => 0, 'total' => 0];
        }

        $query = FeedbackIdentityRevealRequest::query();

        return [
            'available' => true,
            'pending' => (int) (clone $query)->where('status', FeedbackIdentityRevealStatus::Pending)->count(),
            'total' => (int) $query->count(),
        ];
    }

    /**
     * @return array{routed: bool, url: ?string}
     */
    private function demoInfo(): array
    {
        foreach (['demo', 'demo.index', 'demo.console'] as $name) {
            if (Route::has($name)) {
                return [
                    'routed' => true,
                    'url' => route($name),
                ];
            }
        }

        return ['routed' => false, 'url' => null];
    }
}
