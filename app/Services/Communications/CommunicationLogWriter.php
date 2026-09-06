<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationLogStatus;
use App\Models\CommunicationLog;
use App\Models\User;

class CommunicationLogWriter
{
    /**
     * Persist one outbound-message row. Called only by ChannelDispatcher.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function write(
        string $type,
        CommunicationChannel $channel,
        ?User $recipient,
        ?string $subject,
        ?string $locale,
        CommunicationLogStatus $status,
        ?string $providerMessageId = null,
        ?string $error = null,
        ?array $metadata = null,
        ?string $id = null,
    ): CommunicationLog {
        return CommunicationLog::query()->create(array_filter([
            'id' => $id,
            'type' => $type,
            'channel' => $channel,
            'recipient_id' => $recipient?->id,
            'subject' => $subject,
            'locale' => $locale,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'error' => $error,
            'metadata' => $metadata,
        ], fn ($value) => $value !== null || is_array($value)));
    }

    public function markOpened(CommunicationLog $log): CommunicationLog
    {
        if ($log->opened_at === null) {
            $log->update(['opened_at' => now()]);

            $announcementId = $log->metadata['announcement_id'] ?? null;
            if (is_string($announcementId) && $log->recipient_id) {
                \App\Models\AnnouncementDelivery::query()
                    ->where('announcement_id', $announcementId)
                    ->where('recipient_id', $log->recipient_id)
                    ->where('channel', $log->channel)
                    ->whereNull('opened_at')
                    ->update(['opened_at' => now()]);
            }
        }

        return $log->fresh();
    }
}
