<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Models\NotificationPreference;
use App\Models\NotificationReminder;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;

class NotificationPreferenceService
{
    /** @var list<string> */
    public const EVENT_KEYS = [
        'announcement.published',
        'reminder.due',
        'live.reminder',
        'test.event',
    ];

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ChannelDispatcher $dispatcher,
        private readonly ChannelPreferenceLookup $lookup,
    ) {}

    public function channelEnabled(User $user, string $eventKey, CommunicationChannel $channel): bool
    {
        return $this->lookup->enabled($user, $eventKey, $channel);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function matrix(User $actor, User $user): array
    {
        $this->assertSelf($actor, $user);

        $rows = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get();

        $out = [];
        foreach (self::EVENT_KEYS as $eventKey) {
            foreach (CommunicationChannel::cases() as $channel) {
                $match = $rows->first(
                    fn (NotificationPreference $row): bool => $row->event_key === $eventKey
                        && $row->channel === $channel
                );
                $out[$eventKey][$channel->value] = $match !== null
                    ? $match->enabled
                    : $this->channelEnabled($user, $eventKey, $channel);
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array{event_key: string, channel: string, enabled: bool}>  $preferences
     * @return array<string, array<string, bool>>
     */
    public function put(User $actor, User $user, array $preferences): array
    {
        $this->assertSelf($actor, $user);

        $this->audit->withAudit($actor, 'notification_preferences.update', function () use ($user, $preferences) {
            foreach ($preferences as $row) {
                $channel = CommunicationChannel::from($row['channel']);
                NotificationPreference::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'event_key' => $row['event_key'],
                        'channel' => $channel,
                    ],
                    ['enabled' => (bool) $row['enabled']]
                );
            }

            return $user;
        }, NotificationPreference::class);

        return $this->matrix($actor, $user->fresh());
    }

    public function schedule(
        User $actor,
        User $user,
        string $subjectType,
        string $subjectId,
        \DateTimeInterface $remindAt,
    ): NotificationReminder {
        $this->assertSelf($actor, $user);

        return $this->audit->withAudit($actor, 'notification_reminder.schedule', function () use ($user, $subjectType, $subjectId, $remindAt) {
            return NotificationReminder::query()->create([
                'user_id' => $user->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'remind_at' => $remindAt,
            ]);
        }, NotificationReminder::class);
    }

    public function cancel(User $actor, NotificationReminder $reminder): void
    {
        $this->assertSelf($actor, $reminder->user ?? User::query()->findOrFail($reminder->user_id));

        $this->audit->withAudit($actor, 'notification_reminder.cancel', function () use ($reminder) {
            $reminder->delete();

            return $reminder;
        }, NotificationReminder::class);
    }

    /**
     * @return Collection<int, NotificationReminder>
     */
    public function upcoming(User $user): Collection
    {
        return NotificationReminder::query()
            ->where('user_id', $user->id)
            ->whereNull('sent_at')
            ->orderBy('remind_at')
            ->get();
    }

    /**
     * Send each due reminder once. Returns how many were fired.
     */
    public function fireDueReminders(?\DateTimeInterface $at = null): int
    {
        $at = $at ?? now();
        $due = NotificationReminder::query()
            ->whereNull('sent_at')
            ->where('remind_at', '<=', $at)
            ->with('user')
            ->get();

        $fired = 0;
        foreach ($due as $reminder) {
            $user = $reminder->user;
            if ($user === null) {
                $reminder->update(['sent_at' => $at]);

                continue;
            }

            $subject = __('communications.reminder_subject');
            $body = __('communications.reminder_body');

            $this->dispatcher->dispatch(
                CommunicationChannel::InApp,
                $user,
                'reminder.due',
                $subject,
                $body,
                ['reminder_id' => $reminder->id, 'subject_type' => $reminder->subject_type, 'subject_id' => $reminder->subject_id],
            );
            $this->dispatcher->dispatch(
                CommunicationChannel::Mail,
                $user,
                'reminder.due',
                $subject,
                $body,
                ['reminder_id' => $reminder->id],
            );

            $reminder->update(['sent_at' => now()]);
            $fired++;
        }

        return $fired;
    }

    private function assertSelf(User $actor, User $user): void
    {
        $this->authorize->authorize($actor, 'notifications.preferences');

        if ($actor->id !== $user->id && ! $actor->isSuperAdmin()) {
            throw new \App\Exceptions\AuthorizationException(__('auth.forbidden'));
        }
    }
}
