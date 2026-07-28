<?php

namespace App\Services\Live;

use App\Enums\EnrollmentStatus;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\SessionRecurrence;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiveSessionService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ZoomClient $zoom,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{title: string, scheduled_start: string|\DateTimeInterface, duration_minutes: int}  $data
     */
    public function schedule(User $actor, CourseOffering $offering, array $data): LiveSession
    {
        $this->authorize->authorize($actor, 'live.schedule');

        $start = Carbon::parse($data['scheduled_start']);
        $duration = (int) $data['duration_minutes'];
        $end = $start->copy()->addMinutes($duration);

        $this->assertNoOverlap($start, $end);

        return DB::transaction(function () use ($actor, $offering, $data, $start, $duration) {
            $meeting = $this->zoom->createMeeting($data['title'], $start, $duration);

            $session = LiveSession::query()->create([
                'offering_id' => $offering->id,
                'title' => $data['title'],
                'scheduled_start' => $start,
                'duration_minutes' => $duration,
                'zoom_meeting_id' => $meeting['id'],
                'zoom_join_url' => $meeting['join_url'],
                'zoom_start_url' => $meeting['start_url'],
            ]);

            $this->audit->write($actor, 'live.schedule', 'LiveSession', $session->id);

            return $session;
        });
    }

    /**
     * @param  array{days_of_week: array<int, int>, start_time: string, duration_minutes: int, start_date: string, end_date: string, title_prefix?: string}  $data
     * @return list<LiveSession>
     */
    public function scheduleRecurrence(User $actor, CourseOffering $offering, array $data): array
    {
        $this->authorize->authorize($actor, 'live.schedule');

        $recurrence = SessionRecurrence::query()->create([
            'offering_id' => $offering->id,
            'days_of_week' => $data['days_of_week'],
            'start_time' => $data['start_time'],
            'duration_minutes' => $data['duration_minutes'],
            'start_date' => Carbon::parse($data['start_date'])->startOfDay(),
            'end_date' => Carbon::parse($data['end_date'])->endOfDay(),
        ]);

        $sessions = [];
        $cursor = $recurrence->start_date->copy();
        $prefix = $data['title_prefix'] ?? 'Live session';

        while ($cursor->lte($recurrence->end_date)) {
            if (in_array((int) $cursor->dayOfWeek, $recurrence->days_of_week, true)) {
                [$h, $m] = array_map('intval', explode(':', $recurrence->start_time));
                $start = $cursor->copy()->setTime($h, $m);
                $sessions[] = $this->schedule($actor, $offering, [
                    'title' => $prefix.' '.$start->toDateString(),
                    'scheduled_start' => $start,
                    'duration_minutes' => $recurrence->duration_minutes,
                ]);
            }
            $cursor->addDay();
        }

        $this->audit->write($actor, 'live.recurrence', 'SessionRecurrence', $recurrence->id);

        return $sessions;
    }

    public function joinUrl(User $user, LiveSession $session): string
    {
        $this->authorize->authorize($user, 'live.join');

        if (! $session->isJoinable()) {
            throw ValidationException::withMessages(['session' => [__('live.join_window_closed')]]);
        }

        $enrolled = Enrollment::query()
            ->where('student_id', $user->id)
            ->where('offering_id', $session->offering_id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->exists();

        $isStaff = $user->isSuperAdmin()
            || $user->hasRole(\App\Enums\RoleType::AdministrativeAdmin)
            || $user->hasRole(\App\Enums\RoleType::Instructor)
            || $user->hasRole(\App\Enums\RoleType::Ta);

        if (! $enrolled && ! $isStaff) {
            throw ValidationException::withMessages(['session' => [__('live.not_enrolled')]]);
        }

        return $isStaff && $session->zoom_start_url
            ? $session->zoom_start_url
            : (string) $session->zoom_join_url;
    }

    public function sendDueReminders(): array
    {
        $sent24 = 0;
        $sent15 = 0;

        $window24 = [now()->addHours(23), now()->addHours(25)];
        foreach (LiveSession::query()
            ->whereNull('reminder_24h_sent_at')
            ->whereBetween('scheduled_start', $window24)
            ->get() as $session) {
            $this->notifyEnrollees($session, 'live.reminder_24h', __('live.reminder_24h_title'), __('live.reminder_24h_body', ['title' => $session->title]));
            $session->update(['reminder_24h_sent_at' => now()]);
            $sent24++;
        }

        $window15 = [now()->addMinutes(10), now()->addMinutes(20)];
        foreach (LiveSession::query()
            ->whereNull('reminder_15m_sent_at')
            ->whereBetween('scheduled_start', $window15)
            ->get() as $session) {
            $this->notifyEnrollees($session, 'live.reminder_15m', __('live.reminder_15m_title'), __('live.reminder_15m_body', ['title' => $session->title]));
            $session->update(['reminder_15m_sent_at' => now()]);
            $sent15++;
        }

        return ['h24' => $sent24, 'm15' => $sent15];
    }

    private function notifyEnrollees(LiveSession $session, string $type, string $title, string $body): void
    {
        $studentIds = Enrollment::query()
            ->where('offering_id', $session->offering_id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->pluck('student_id');

        foreach (User::query()->whereIn('id', $studentIds)->get() as $user) {
            $this->notifications->notify($user, $type, $title, $body, [
                'live_session_id' => $session->id,
            ]);
        }
    }

    private function assertNoOverlap(Carbon $start, Carbon $end): void
    {
        $hosts = (int) (Setting::query()->find('zoom.concurrent_hosts')?->value['value'] ?? 1);
        if ($hosts < 1) {
            $hosts = 1;
        }

        $overlapping = LiveSession::query()->get()->filter(function (LiveSession $existing) use ($start, $end) {
            $eStart = $existing->scheduled_start;
            $eEnd = $existing->endsAt();

            return $start->lt($eEnd) && $end->gt($eStart);
        })->count();

        if ($overlapping >= $hosts) {
            throw ValidationException::withMessages(['session' => [__('live.overlap_blocked')]]);
        }
    }
}
