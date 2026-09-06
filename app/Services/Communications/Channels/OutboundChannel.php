<?php

namespace App\Services\Communications\Channels;

use App\Models\Notification;
use App\Models\User;

interface OutboundChannel
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{ok: bool, provider_message_id: ?string, error: ?string, notification: ?Notification}
     */
    public function send(
        User $recipient,
        string $type,
        string $subject,
        string $body,
        ?array $metadata = null,
    ): array;
}
