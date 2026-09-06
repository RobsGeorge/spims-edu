<?php

namespace App\Services\Notifications;

use App\Enums\CommunicationChannel;
use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;
use App\Services\Communications\ChannelDispatcher;

class NotificationService
{
    public function __construct(
        private readonly ChannelDispatcher $dispatcher,
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
        $this->dispatcher->dispatch(
            CommunicationChannel::InApp,
            $user,
            $type,
            $title,
            $body,
            $metadata,
            respectPreferences: true,
        );

        if ($alsoEmail) {
            $this->dispatcher->dispatch(
                CommunicationChannel::Mail,
                $user,
                $type,
                $title,
                $body,
                $metadata,
                respectPreferences: true,
            );
        }

        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('channel', NotificationChannel::InApp)
            ->latest('created_at')
            ->first();

        if ($notification !== null) {
            return $notification;
        }

        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->latest('created_at')
            ->firstOrFail();
    }

    public function markRead(User $user, Notification $notification): Notification
    {
        abort_unless($notification->user_id === $user->id || $user->isSuperAdmin(), 403);
        $notification->update(['read_at' => now()]);

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', NotificationChannel::InApp)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
