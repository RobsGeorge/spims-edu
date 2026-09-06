<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Resolves whether a user wants a given event on a channel.
 *
 * A per-event `notification_preferences` row wins. Until one exists, mail
 * falls back to `users.notify_email`, in_app stays on, and whatsapp stays off.
 */
class ChannelPreferenceLookup
{
    public function enabled(User $user, string $eventKey, CommunicationChannel $channel): bool
    {
        $row = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_key', $eventKey)
            ->where('channel', $channel->value)
            ->first();

        if ($row !== null) {
            return $row->enabled;
        }

        return match ($channel) {
            CommunicationChannel::Mail => $user->notify_email ?? true,
            CommunicationChannel::Whatsapp => false,
            CommunicationChannel::InApp => true,
        };
    }
}
