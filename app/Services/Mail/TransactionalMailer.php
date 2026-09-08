<?php

namespace App\Services\Mail;

use App\Services\SuperAdmin\IntegrationConfigService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionalMailer
{
    public function __construct(
        private readonly IntegrationConfigService $integrations,
    ) {}

    /**
     * Send a plain-text transactional message.
     * Missing/broken mailers degrade (never block). MAIL_MAILER=log writes to the
     * dedicated mail channel (storage/logs/mail.log), not laravel.log.
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        string $identity = IntegrationConfigService::IDENTITY_TRANSACTIONAL,
    ): bool {
        $this->integrations->applyRuntime();

        $fromAddress = $this->integrations->fromAddress($identity);
        $fromName = $this->integrations->fromName($identity);

        try {
            Mail::raw($body, function ($message) use ($to, $subject, $fromAddress, $fromName): void {
                $message->from($fromAddress, $fromName);
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Transactional mail send failed', [
                'to' => $to,
                'subject' => $subject,
                'identity' => $identity,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
