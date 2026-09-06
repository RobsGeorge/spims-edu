<?php

namespace App\Exceptions;

use App\Support\Api\ApiError;
use App\Support\Api\ApiLocale;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizationException extends Exception
{
    /**
     * Laravel checks an exception's own `render()` before any `renderable()`
     * callback registered in the Handler (see `Handler::render()`), so the api/v1
     * envelope has to be special-cased here rather than in the Handler — a
     * renderable callback would never be reached for this exception.
     */
    public function render(Request $request): Response
    {
        if ($request->is('api/v1/*')) {
            // The message is set at throw time (see AuthorizeService), typically
            // via __('auth.forbidden') resolved in whatever locale was active
            // then — which may not be this request's. Re-resolve for api/v1 so
            // the response is never accidentally in the wrong language.
            $message = $this->getMessage() === __('auth.forbidden')
                ? __('auth.forbidden', [], ApiLocale::resolve($request))
                : $this->getMessage();

            return ApiError::response($request, 'FORBIDDEN', $message, 403);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 403);
        }

        abort(403, $this->getMessage());
    }
}
