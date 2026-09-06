<?php

namespace App\Services\Communications\Channels;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;

class InAppChannel implements OutboundChannel
{
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
            'channel' => NotificationChannel::InApp,
            'metadata' => $metadata,
        ]);

        return [
            'ok' => true,
            'provider_message_id' => $notification->id,
            'error' => null,
            'notification' => $notification,
        ];
    }
}
