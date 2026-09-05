<?php

namespace App\Exceptions;

use App\Support\Api\ApiError;
use App\Support\Api\ApiLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // One error shape for every /api/v1 failure. Every branch below returns
        // null for a non-v1 request, leaving Laravel's normal handling — including
        // any web view, redirect, or non-versioned JSON — completely untouched.
        //
        // App\Exceptions\AuthorizationException is deliberately NOT handled here:
        // it defines its own render(), which Laravel checks before any renderable
        // callback runs (see Handler::render() in the framework), so a callback
        // registered here would never fire for it.

        $this->renderable(function (ValidationException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return ApiError::response(
                $request,
                'VALIDATION_FAILED',
                $e->getMessage(),
                422,
                errors: $e->errors(),
            );
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            $locale = ApiLocale::resolve($request);

            return ApiError::response($request, 'UNAUTHENTICATED', __('auth.unauthorized', [], $locale), 401);
        });

        // Catches NotFoundHttpException, MethodNotAllowedHttpException,
        // ThrottleRequestsException (429, with Retry-After preserved), and any other
        // Symfony HTTP exception — but never AuthorizationException, which never
        // reaches renderable callbacks in the first place.
        $this->renderable(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            return ApiError::response(
                $request,
                self::codeForStatus($status),
                self::messageForStatus($status, $e->getMessage(), ApiLocale::resolve($request)),
                $status,
                headers: $e->getHeaders(),
            );
        });
    }

    /**
     * Covers every status this handler actually maps a Symfony HTTP exception to.
     * 401/403 matter here even though App\Exceptions\AuthorizationException (this
     * app's own) never reaches this method (see the class-level note above) —
     * Laravel's own Illuminate\Auth\Access\AuthorizationException, thrown by
     * Policies, Gate::authorize(), and the `can:` middleware, has no render() of
     * its own and is converted to AccessDeniedHttpException by prepareException(),
     * which *does* arrive here. Without an explicit 403 mapping it would have
     * fallen to the generic 'ERROR' code — a different code than this app's own
     * AuthorizationException for the identical HTTP status, contradicting the
     * "one error shape" premise the moment either is used.
     */
    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            423 => 'LOCKED',
            429 => 'RATE_LIMITED',
            default => $status >= 500 ? 'SERVER_ERROR' : 'ERROR',
        };
    }

    /**
     * A 404 from a missing route-bound model carries the model's fully-qualified
     * class name in its message ("No query results for model [App\Models\...]").
     * That is fine on a Blade error page; it is not something to hand a client
     * probing IDs, so 404s get a fixed generic message instead of the raw one.
     */
    private static function messageForStatus(int $status, string $raw, string $locale): string
    {
        if ($status === 404) {
            return __('api.not_found', [], $locale);
        }

        return $raw !== '' ? $raw : self::codeForStatus($status);
    }
}
