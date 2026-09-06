<?php

namespace App\Http\Middleware\Api;

use App\Support\Api\ApiLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale for the happy path of an /api/v1 request (controllers that
 * localize their own response content). Deliberately not added to the shared
 * `api` middleware group, so it cannot affect the legacy `/api/user` scaffold or
 * the unversioned `/api/branding` route.
 *
 * This does NOT make locale-aware exception rendering safe on its own — see
 * ApiLocale for why the Handler and AuthorizationException resolve it directly
 * rather than trusting that this middleware ran first.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(ApiLocale::resolve($request));

        return $next($request);
    }
}
