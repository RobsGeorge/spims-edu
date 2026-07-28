<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case EmailVerification = 'EMAIL_VERIFICATION';
    case PasswordReset = 'PASSWORD_RESET';
}
