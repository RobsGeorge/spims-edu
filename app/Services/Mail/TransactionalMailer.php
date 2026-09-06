<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalMailer
{
    /**
     * Send a plain-text transactional message.
     * Missing/broken mailers degrade (never block). MAIL_MAILER=log writes to the
     * dedicated mail channel (storage/logs/mail.log), not laravel.log.
     */
    public function send(string $to, string $subject, string $body): bool
    {
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
