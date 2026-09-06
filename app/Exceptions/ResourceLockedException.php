<?php

namespace App\Exceptions;

use App\Support\Api\ApiError;
use App\Support\Api\ApiLocale;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResourceLockedException extends Exception
{
    public function render(Request $request): Response
    {
        if ($request->is('api/v1/*')) {
            $message = $this->getMessage() !== ''
                ? $this->getMessage()
                : __('attendance.session_closed', [], ApiLocale::resolve($request));

            return ApiError::response($request, 'LOCKED', $message, 423);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 423);
        }

        abort(423, $this->getMessage());
    }
}
