<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthProbe
{
    /**
     * Same probes the public /health JSON uses. Super Admin embeds this
     * snapshot; monitors keep hitting the public route.
     *
     * @return array{status: string, ok: bool, checks: array{app: bool, database: bool, cache: bool}, timestamp: string}
     */
    public function snapshot(): array
    {
        $checks = [
            'app' => true,
            'database' => false,
            'cache' => false,
        ];

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
            $checks['database'] = false;
        }

        try {
            if (config('cache.default') === 'redis') {
                Redis::connection()->ping();
                $checks['cache'] = true;
            } else {
                cache()->put('health_ping', 'ok', 5);
                $checks['cache'] = cache()->get('health_ping') === 'ok';
            }
        } catch (Throwable) {
            $checks['cache'] = false;
        }

        $ok = $checks['app'] && $checks['database'];

        return [
            'status' => $ok ? 'ok' : 'degraded',
            'ok' => $ok,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
