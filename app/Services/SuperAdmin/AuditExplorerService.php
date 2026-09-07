<?php

namespace App\Services\SuperAdmin;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\AuthorizeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AuditExplorerService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate($actor, array $filters, int $perPage = 40): LengthAwarePaginator
    {
        $this->authorize->authorize($actor, 'audit.view');

        return $this->filteredQuery($filters)
            ->with('actor')
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function matchingCount($actor, array $filters): int
    {
        $this->authorize->authorize($actor, 'audit.view');

        return $this->filteredQuery($filters)->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: Collection<int, AuditLog>, truncated: bool, cap: int, matched: int}
     */
    public function export($actor, array $filters): array
    {
        $this->authorize->authorize($actor, 'audit.export');

        $cap = $this->exportCap();
        $matched = $this->filteredQuery($filters)->count();
        $rows = $this->filteredQuery($filters)
            ->with('actor')
            ->latest('created_at')
            ->limit($cap)
            ->get();

        return [
            'rows' => $rows,
            'truncated' => $matched > $cap,
            'cap' => $cap,
            'matched' => $matched,
        ];
    }

    public function retentionDays(): int
    {
        $setting = Setting::query()->find('audit.retention_days');
        $value = $setting?->value['value'] ?? config('spims.audit.retention_days', 365);

        return max(1, (int) $value);
    }

    public function exportCap(): int
    {
        return max(1, (int) config('spims.audit.export_cap', 10000));
    }

    /**
     * @return list<string>
     */
    public function protectedPrefixes(): array
    {
        return array_values(array_filter(array_map(
            'strval',
            (array) config('spims.audit.protected_prefixes', [])
        )));
    }

    /**
     * Delete ordinary rows older than retention. Control-plane prefixes are kept
     * unless they are older than three times the retention floor.
     *
     * @return array{deleted: int, kept_protected: int, retention_days: int}
     */
    public function prune(?int $days = null): array
    {
        $days = $days ?? $this->retentionDays();
        $ordinaryCutoff = now()->subDays($days);
        $protectedCutoff = now()->subDays($days * 3);
        $prefixes = $this->protectedPrefixes();

        $protectedQuery = AuditLog::query()->where(function (Builder $query) use ($prefixes): void {
            foreach ($prefixes as $prefix) {
                $query->orWhere('action', 'like', $prefix.'%');
            }
        });

        $keptProtected = (clone $protectedQuery)
            ->where('created_at', '<', $ordinaryCutoff)
            ->where('created_at', '>=', $protectedCutoff)
            ->count();

        $deletedProtected = 0;
        if ($prefixes !== []) {
            $deletedProtected = (clone $protectedQuery)
                ->where('created_at', '<', $protectedCutoff)
                ->delete();
        }

        $ordinary = AuditLog::query()->where('created_at', '<', $ordinaryCutoff);
        foreach ($prefixes as $prefix) {
            $ordinary->where('action', 'not like', $prefix.'%');
        }
        $deletedOrdinary = $ordinary->delete();

        return [
            'deleted' => $deletedOrdinary + $deletedProtected,
            'kept_protected' => $keptProtected,
            'retention_days' => $days,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredQuery(array $filters): Builder
    {
        $query = AuditLog::query();

        $actorEmail = trim((string) ($filters['actor'] ?? ''));
        if ($actorEmail !== '') {
            $like = '%'.mb_strtolower($actorEmail).'%';
            $query->whereHas('actor', function (Builder $actor) use ($like): void {
                $actor->whereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $query->where('action', 'like', $action.'%');
        }

        $entityType = trim((string) ($filters['entity_type'] ?? ''));
        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        $entityId = trim((string) ($filters['entity_id'] ?? ''));
        if ($entityId !== '') {
            $query->where('entity_id', $entityId);
        }

        $requestId = trim((string) ($filters['request_id'] ?? ''));
        if ($requestId !== '') {
            $query->where('request_id', $requestId);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }
}
