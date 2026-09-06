<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationLogStatus;
use App\Models\CommunicationLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\Communications\Channels\InAppChannel;
use App\Services\Communications\Channels\MailChannel;
use App\Services\Communications\Channels\OutboundChannel;
use Illuminate\Support\Str;

class ChannelDispatcher
{
    /** @var array<string, OutboundChannel|null> */
    private array $drivers;

    public function __construct(
        private readonly CommunicationLogWriter $logs,
        private readonly ChannelPreferenceLookup $preferences,
        InAppChannel $inApp,
        MailChannel $mail,
    ) {
        $this->drivers = [
            CommunicationChannel::InApp->value => $inApp,
            CommunicationChannel::Mail->value => $mail,
            // Registered so callers can name the channel; no driver ships.
            CommunicationChannel::Whatsapp->value => null,
        ];
    }

    public function hasDriver(CommunicationChannel $channel): bool
    {
        return ($this->drivers[$channel->value] ?? null) instanceof OutboundChannel;
    }

    /**
     * Fan out one message on one channel. WhatsApp (and any other named
     * channel without a driver) records a skipped log and never throws.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function dispatch(
        CommunicationChannel $channel,
        User $recipient,
        string $type,
        string $subject,
        string $body,
        ?array $metadata = null,
        ?string $locale = null,
        bool $respectPreferences = true,
    ): CommunicationLog {
        $locale ??= $recipient->preferred_locale ?: app()->getLocale();
        $logId = (string) Str::ulid();
        $metadata = $metadata ?? [];
        $metadata['communication_log_id'] = $logId;

        if ($respectPreferences && ! $this->preferences->enabled($recipient, $type, $channel)) {
            return $this->logs->write(
                type: $type,
                channel: $channel,
                recipient: $recipient,
                subject: $subject,
                locale: $locale,
                status: CommunicationLogStatus::Skipped,
                error: 'preference_disabled',
                metadata: $metadata,
                id: $logId,
            );
        }

        $driver = $this->drivers[$channel->value] ?? null;
        if (! $driver instanceof OutboundChannel) {
            return $this->logs->write(
                type: $type,
                channel: $channel,
                recipient: $recipient,
                subject: $subject,
                locale: $locale,
                status: CommunicationLogStatus::Skipped,
                error: 'unimplemented',
                metadata: $metadata,
                id: $logId,
            );
        }

        if ($channel === CommunicationChannel::Mail) {
            $body .= $this->trackingPixel($logId);
        }

        try {
            $result = $driver->send($recipient, $type, $subject, $body, $metadata);
        } catch (\Throwable $e) {
            return $this->logs->write(
                type: $type,
                channel: $channel,
                recipient: $recipient,
                subject: $subject,
                locale: $locale,
                status: CommunicationLogStatus::Failed,
                error: $e->getMessage(),
                metadata: $metadata,
                id: $logId,
            );
        }

        return $this->logs->write(
            type: $type,
            channel: $channel,
            recipient: $recipient,
            subject: $subject,
            locale: $locale,
            status: $result['ok'] ? CommunicationLogStatus::Sent : CommunicationLogStatus::Failed,
            providerMessageId: $result['provider_message_id'],
            error: $result['error'],
            metadata: $metadata,
            id: $logId,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function lastInAppNotification(User $recipient, string $type): ?Notification
    {
        return Notification::query()
            ->where('user_id', $recipient->id)
            ->where('type', $type)
            ->where('channel', \App\Enums\NotificationChannel::InApp)
            ->latest('created_at')
            ->first();
    }

    private function trackingPixel(string $logId): string
    {
        try {
            $url = route('communications.open', $logId, absolute: false);
        } catch (\Throwable) {
            $url = '/communications/open/'.$logId;
        }

        return "\n\n".'<img src="'.$url.'" width="1" height="1" alt="" />';
    }
}
