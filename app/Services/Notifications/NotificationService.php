<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
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

        // Mail is skipped/logged in v1 — never blocks.
        Log::info('Notification email', [
            'to' => $user->email,
            'type' => $type,
            'title' => $title,
        ]);

        if (config('mail.default') !== 'array' && config('mail.default') !== 'log') {
            try {
                Mail::raw($body, function ($message) use ($user, $title) {
                    $message->to($user->email)->subject($title);
                });
            } catch (\Throwable $e) {
                Log::warning('Notification email send failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
