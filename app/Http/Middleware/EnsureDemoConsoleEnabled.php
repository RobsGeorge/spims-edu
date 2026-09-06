<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoConsoleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('spims.demo_console')) {
            abort(404);
        }

        return $next($request);
    }
}
