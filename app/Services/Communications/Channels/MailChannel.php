<?php

namespace App\Services\Communications\Channels;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;
use App\Services\SuperAdmin\IntegrationConfigService;

class MailChannel implements OutboundChannel
{
    public function __construct(
        private readonly TransactionalMailer $mailer,
    ) {}

    public function send(
        User $recipient,
        string $type,
        string $subject,
        string $body,
        ?array $metadata = null,
    ): array {
        $notification = Notification::query()->create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $subject,
            'body' => $body,
            'channel' => NotificationChannel::Email,
            'metadata' => $metadata,
        ]);

        $sent = $this->mailer->send(
            (string) $recipient->email,
            $subject,
            $body,
            IntegrationConfigService::IDENTITY_NOTIFICATIONS
        );

        return [
            'ok' => $sent,
            'provider_message_id' => $notification->id,
            'error' => $sent ? null : 'mail_failed',
            'notification' => $notification,
        ];
    }
}
