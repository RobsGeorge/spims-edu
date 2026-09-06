<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One error shape for every /api/v1 failure:
 *
 *     { "message": "...", "code": "FORBIDDEN", "errors": {...}? }
 *
 * `errors` is present only for 422s. `$message` must already be localized by the
 * caller — see ApiLocale for why that has to be resolved explicitly at the point
 * of throwing/rendering rather than trusted from `App::getLocale()`.
 */
final class ApiError
{
    public static function response(
        Request $request,
        string $code,
        string $message,
        int $status,
        ?array $errors = null,
        array $headers = [],
    ): JsonResponse {
        $body = ['message' => $message, 'code' => $code];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, $headers);
    }
}
