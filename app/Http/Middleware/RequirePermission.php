<?php

namespace App\Http\Middleware;

use App\Support\AuthorizeService;
use App\Support\Scope\ResourceScopeResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly ResourceScopeResolver $scope,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $this->authorize->authorize(
            $request->user(),
            $permission,
            $this->resourceFor($request, $permission),
        );

        return $next($request);
    }

    /**
     * For an offering-scoped permission, hand the authorizer the bound model the route is
     * acting on. Without this the guard sees no resource and fails closed, which would
     * lock scoped roles out of routes they legitimately own.
     */
    private function resourceFor(Request $request, string $permission): mixed
    {
        if (! in_array($permission, (array) config('permission_scopes.offering_scoped', []), true)) {
            return null;
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (is_object($parameter) && $this->scope->offeringIdsFor($parameter) !== []) {
                return $parameter;
            }
        }

        return null;
    }
}
