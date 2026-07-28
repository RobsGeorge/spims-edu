<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
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

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
