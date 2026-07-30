<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;

class NotificationService
{
    public function __construct(
        private readonly TransactionalMailer $mailer,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function notify(
        User $user,
        string $type,
        string $title,
        string $body,
        ?array $metadata = null,
        bool $alsoEmail = true,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'channel' => NotificationChannel::InApp,
            'metadata' => $metadata,
        ]);

        if ($alsoEmail) {
            $this->sendEmailChannel($user, $type, $title, $body, $metadata);
        }

        return $notification;
    }

    public function markRead(User $user, Notification $notification): Notification
    {
        abort_unless($notification->user_id === $user->id || $user->isSuperAdmin(), 403);
        $notification->update(['read_at' => now()]);

        return $notification->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function sendEmailChannel(User $user, string $type, string $title, string $body, ?array $metadata): void
    {
        Notification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'channel' => NotificationChannel::Email,
            'metadata' => $metadata,
        ]);

        $this->mailer->send((string) $user->email, $title, $body);
    }
}
