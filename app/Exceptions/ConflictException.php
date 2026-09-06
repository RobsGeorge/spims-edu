<?php

namespace App\Exceptions;

use App\Support\Api\ApiError;
use App\Support\Api\ApiLocale;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConflictException extends Exception
{
    public function render(Request $request): Response
    {
        if ($request->is('api/v1/*')) {
            $message = $this->getMessage() !== ''
                ? $this->getMessage()
                : __('attendance.stale_write', [], ApiLocale::resolve($request));

            return ApiError::response($request, 'CONFLICT', $message, 409);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        abort(409, $this->getMessage());
    }
}
