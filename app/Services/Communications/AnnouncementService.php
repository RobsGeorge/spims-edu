<?php

namespace App\Services\Communications;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementTargetType;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationLogStatus;
use App\Enums\DeliveryStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\RoleType;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementRevision;
use App\Models\AnnouncementTarget;
use App\Models\CommunicationLog;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Models\UserRole;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ChannelDispatcher $dispatcher,
        private readonly EmailTemplateService $templates,
    ) {}

    /**
     * @param  array{title: string, body: string, body_ar?: ?string, body_fr?: ?string, is_banner?: bool, banner_expires_at?: mixed, targets?: list<array{type: string, id: string}>}  $data
     */
    public function draft(User $actor, CourseOffering $offering, array $data): Announcement
    {
        $this->authorize->authorize($actor, 'announcements.manage', $offering);

        return $this->audit->withAudit($actor, 'announcement.create', function () use ($actor, $offering, $data) {
            $announcement = Announcement::query()->create([
                'offering_id' => $offering->id,
                'author_id' => $actor->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'body_ar' => $data['body_ar'] ?? null,
                'body_fr' => $data['body_fr'] ?? null,
                'status' => AnnouncementStatus::Draft,
                'is_banner' => (bool) ($data['is_banner'] ?? false),
                'banner_expires_at' => $data['banner_expires_at'] ?? null,
            ]);

            $this->syncTargets($announcement, $data['targets'] ?? [
                ['type' => AnnouncementTargetType::Offering->value, 'id' => $offering->id],
            ]);

            return $announcement->fresh(['targets']);
        }, Announcement::class);
    }

    /**
     * @param  array{title?: string, body?: string, body_ar?: ?string, body_fr?: ?string, is_banner?: bool, banner_expires_at?: mixed, targets?: list<array{type: string, id: string}>}  $data
     */
    public function update(User $actor, Announcement $announcement, array $data): Announcement
    {
        $this->authorize->authorize($actor, 'announcements.manage', $announcement);

        return $this->audit->withAudit($actor, 'announcement.update', function () use ($actor, $announcement, $data) {
            AnnouncementRevision::query()->create([
                'announcement_id' => $announcement->id,
                'editor_id' => $actor->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'body_ar' => $announcement->body_ar,
                'body_fr' => $announcement->body_fr,
            ]);

            $announcement->fill([
                'title' => $data['title'] ?? $announcement->title,
                'body' => $data['body'] ?? $announcement->body,
            ]);
            if (array_key_exists('body_ar', $data)) {
                $announcement->body_ar = $data['body_ar'];
            }
            if (array_key_exists('body_fr', $data)) {
                $announcement->body_fr = $data['body_fr'];
            }
            if (array_key_exists('is_banner', $data)) {
                $announcement->is_banner = (bool) $data['is_banner'];
            }
            if (array_key_exists('banner_expires_at', $data)) {
                $announcement->banner_expires_at = $data['banner_expires_at'];
            }
            $announcement->save();

            if (isset($data['targets'])) {
                $this->syncTargets($announcement, $data['targets']);
            }

            return $announcement->fresh(['targets', 'revisions']);
        }, Announcement::class);
    }

    /**
     * @param  list<string>|null  $channels
     */
    public function publish(User $actor, Announcement $announcement, ?array $channels = null): Announcement
    {
        $this->authorize->authorize($actor, 'announcements.publish', $announcement);

        if ($announcement->isPublished()) {
            throw ValidationException::withMessages([
                'status' => [__('communications.already_published')],
            ]);
        }

        $channels = $channels ?? [
            CommunicationChannel::InApp->value,
            CommunicationChannel::Mail->value,
        ];

        return $this->audit->withAudit($actor, 'announcement.publish', function () use ($actor, $announcement, $channels) {
            $announcement->update([
                'status' => AnnouncementStatus::Published,
                'published_at' => now(),
                'published_by_id' => $actor->id,
            ]);

            $recipients = $this->resolveRecipients($announcement);
            $announcement->loadMissing('offering.course');

            foreach ($recipients as $recipient) {
                foreach ($channels as $channelName) {
                    $channel = CommunicationChannel::from($channelName);
                    $this->deliver($announcement, $recipient, $channel);
                }
            }

            return $announcement->fresh(['deliveries', 'targets']);
        }, Announcement::class);
    }

    public function resendEmail(User $actor, Announcement $announcement): Announcement
    {
        $this->authorize->authorize($actor, 'announcements.publish', $announcement);

        if (! $announcement->isPublished()) {
            throw ValidationException::withMessages([
                'status' => [__('communications.not_published')],
            ]);
        }

        return $this->audit->withAudit($actor, 'announcement.resend', function () use ($announcement) {
            $recipients = $this->resolveRecipients($announcement);
            $announcement->loadMissing('offering.course');

            foreach ($recipients as $recipient) {
                $this->deliver($announcement, $recipient, CommunicationChannel::Mail, resend: true);
            }

            return $announcement->fresh(['deliveries']);
        }, Announcement::class);
    }

    public function dismissBanner(User $actor, Announcement $announcement): Announcement
    {
        $this->authorize->authorize($actor, 'announcements.view');

        $delivery = AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->id)
            ->where('recipient_id', $actor->id)
            ->where('channel', CommunicationChannel::InApp)
            ->first();

        if ($delivery === null) {
            throw new \App\Exceptions\AuthorizationException(__('auth.forbidden'));
        }

        if ($delivery->read_at === null) {
            $delivery->update(['read_at' => now()]);
        }

        return $announcement;
    }

    /**
     * Published announcements delivered to this user (student inbox).
     *
     * @return Collection<int, Announcement>
     */
    public function inboxFor(User $user, bool $bannersOnly = false): Collection
    {
        $this->authorize->authorize($user, 'announcements.view');

        $query = Announcement::query()
            ->where('status', AnnouncementStatus::Published)
            ->whereHas('deliveries', function ($q) use ($user) {
                $q->where('recipient_id', $user->id)
                    ->where('channel', CommunicationChannel::InApp);
            })
            ->with(['offering.course', 'author'])
            ->orderByDesc('published_at');

        if ($bannersOnly) {
            $query->where('is_banner', true)
                ->where(function ($q) {
                    $q->whereNull('banner_expires_at')->orWhere('banner_expires_at', '>', now());
                })
                ->whereHas('deliveries', function ($q) use ($user) {
                    $q->where('recipient_id', $user->id)
                        ->where('channel', CommunicationChannel::InApp)
                        ->whereNull('read_at');
                });
        }

        return $query->get();
    }

    public function showFor(User $user, Announcement $announcement): Announcement
    {
        $this->authorize->authorize($user, 'announcements.view');

        $delivered = $announcement->deliveries()
            ->where('recipient_id', $user->id)
            ->where('channel', CommunicationChannel::InApp)
            ->exists();

        if (! $delivered || ! $announcement->isPublished()) {
            throw new \App\Exceptions\AuthorizationException(__('auth.forbidden'));
        }

        return $announcement->load(['offering.course', 'author']);
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveRecipients(Announcement $announcement): Collection
    {
        $ids = collect();

        $targets = $announcement->targets()->get();
        if ($targets->isEmpty()) {
            $targets = collect([
                new AnnouncementTarget([
                    'target_type' => AnnouncementTargetType::Offering,
                    'target_id' => $announcement->offering_id,
                ]),
            ]);
        }

        foreach ($targets as $target) {
            $ids = $ids->merge($this->idsForTarget($target));
        }

        $unique = $ids->unique()->filter()->values();

        return User::query()->whereIn('id', $unique->all())->get();
    }

    /**
     * @return list<string>
     */
    private function idsForTarget(AnnouncementTarget $target): array
    {
        return match ($target->target_type) {
            AnnouncementTargetType::Offering => Enrollment::query()
                ->where('offering_id', $target->target_id)
                ->where('status', EnrollmentStatus::Enrolled)
                ->pluck('student_id')
                ->all(),
            AnnouncementTargetType::Program => StudentProgram::query()
                ->where('program_id', $target->target_id)
                ->pluck('student_id')
                ->all(),
            AnnouncementTargetType::Semester => Enrollment::query()
                ->where('status', EnrollmentStatus::Enrolled)
                ->whereHas('offering', fn ($q) => $q->where('semester_id', $target->target_id))
                ->pluck('student_id')
                ->all(),
            AnnouncementTargetType::Role => UserRole::query()
                ->where('role', RoleType::from($target->target_id))
                ->pluck('user_id')
                ->all(),
            AnnouncementTargetType::User => [$target->target_id],
        };
    }

    /**
     * @param  list<array{type: string, id: string}>  $targets
     */
    private function syncTargets(Announcement $announcement, array $targets): void
    {
        $announcement->targets()->delete();

        foreach ($targets as $target) {
            AnnouncementTarget::query()->create([
                'announcement_id' => $announcement->id,
                'target_type' => AnnouncementTargetType::from($target['type']),
                'target_id' => $target['id'],
            ]);
        }
    }

    private function deliver(
        Announcement $announcement,
        User $recipient,
        CommunicationChannel $channel,
        bool $resend = false,
    ): AnnouncementDelivery {
        $existing = AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->id)
            ->where('recipient_id', $recipient->id)
            ->where('channel', $channel)
            ->first();

        if ($existing !== null && ! $resend) {
            return $existing;
        }

        if ($existing !== null && $resend && $channel !== CommunicationChannel::Mail) {
            return $existing;
        }

        $locale = $recipient->preferred_locale ?: 'en';
        $resolved = $this->templates->resolve('announcement.published', $locale, $announcement->offering);
        $rendered = $this->templates->render($resolved['subject'], $resolved['body'], [
            'name' => trim($recipient->first_name.' '.$recipient->last_name),
            'first_name' => $recipient->first_name,
            'last_name' => $recipient->last_name,
            'email' => $recipient->email,
            'title' => $announcement->title,
            'body' => $announcement->localizedBody($locale),
            'course' => $announcement->offering?->course?->title ?? '',
            'offering' => $announcement->offering?->course?->code ?? '',
            'locale' => $locale,
        ]);

        $log = $this->dispatcher->dispatch(
            $channel,
            $recipient,
            'announcement.published',
            $rendered['subject'],
            $rendered['body'],
            ['announcement_id' => $announcement->id],
            $locale,
        );

        $status = match ($log->status) {
            CommunicationLogStatus::Sent => DeliveryStatus::Sent,
            CommunicationLogStatus::Skipped => DeliveryStatus::Skipped,
            CommunicationLogStatus::Failed => DeliveryStatus::Failed,
        };

        $payload = [
            'status' => $status,
            'sent_at' => $status === DeliveryStatus::Sent ? now() : $existing?->sent_at,
            'error' => $log->error,
        ];

        if ($existing !== null) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return AnnouncementDelivery::query()->create([
            'announcement_id' => $announcement->id,
            'recipient_id' => $recipient->id,
            'channel' => $channel,
            'status' => $status,
            'sent_at' => $payload['sent_at'],
            'error' => $log->error,
        ]);
    }

}
