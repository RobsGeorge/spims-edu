<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInstructorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $allowed = $user->tokenCan('role:INSTRUCTOR')
            || $user->tokenCan('role:TA')
            || $user->tokenCan('role:ACADEMIC_ADMIN')
            || $user->tokenCan('role:SUPER_ADMIN');

        abort_unless($allowed, 403);

        return $next($request);
    }
}
