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

        if ($alsoEmail && $this->wantsEmail($user)) {
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
     * Honours the `notify_email` preference exposed in settings. Users predating the
     * column default to opted-in, matching the column default.
     */
    private function wantsEmail(User $user): bool
    {
        return $user->notify_email ?? true;
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
