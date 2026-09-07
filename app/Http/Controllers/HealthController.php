<?php

namespace App\Http\Controllers;

use App\Support\HealthProbe;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthProbe $probe): JsonResponse
    {
        $snapshot = $probe->snapshot();

        return response()->json([
            'status' => $snapshot['status'],
            'checks' => $snapshot['checks'],
            'timestamp' => $snapshot['timestamp'],
        ], $snapshot['ok'] ? 200 : 503);
    }
}
