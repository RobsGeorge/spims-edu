<?php

namespace App\Http\Middleware;

use App\Support\AuthorizeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private readonly AuthorizeService $authorize) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $this->authorize->authorize($request->user(), $permission);

        return $next($request);
    }
}
