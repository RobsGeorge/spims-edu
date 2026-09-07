<?php

namespace App\Http\Middleware;

use App\Services\SuperAdmin\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(
        private readonly FeatureFlagService $flags,
    ) {}

    public function handle(Request $request, Closure $next, string $flag): Response
    {
        if (! $this->flags->enabled($flag)) {
            abort(404);
        }

        return $next($request);
    }
}
