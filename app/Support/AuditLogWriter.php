<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogWriter
{
    public function write(
        ?User $actor,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $actorRole = null,
    ): AuditLog {
        $request = request();

        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_role' => $actorRole,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get('request_id'),
        ]);
    }

    public function withAudit(?User $actor, string $action, Closure $callback, ?string $entityType = null): mixed
    {
        return DB::transaction(function () use ($actor, $action, $callback, $entityType) {
            $result = $callback();

            $entityId = null;
            $after = null;

            if (is_object($result) && method_exists($result, 'getKey')) {
                $entityId = (string) $result->getKey();
                $after = method_exists($result, 'toArray') ? $result->toArray() : null;
            }

            $this->write(
                actor: $actor,
                action: $action,
                entityType: $entityType,
                entityId: $entityId,
                after: $after,
                actorRole: $actor?->roleTypes()->first()?->value,
            );

            return $result;
        });
    }
}
