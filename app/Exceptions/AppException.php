<?php

namespace App\Exceptions;

use Exception;

class AppException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'app_error',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
