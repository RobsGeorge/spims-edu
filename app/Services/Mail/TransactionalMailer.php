<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalMailer
{
    /**
     * Send a plain-text transactional message.
     * When MAIL_MAILER is log/array, only logs (OTP/dev path stays green).
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            Log::info('Transactional mail (logged, not sent)', [
                'to' => $to,
                'subject' => $subject,
                'mailer' => $mailer,
            ]);

            return true;
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Transactional mail send failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
