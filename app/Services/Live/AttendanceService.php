<?php

namespace App\Services\Live;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AttendanceRecord;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @param  array<int, array{email?: string, user_id?: string, minutes: int}>  $participants
     */
    public function importFromZoom(User $actor, LiveSession $session, array $participants): int
    {
        $this->authorize->authorize($actor, 'attendance.manage');

        $threshold = $this->thresholdPercent($session->offering);
        $requiredMinutes = max(1, (int) ceil($session->duration_minutes * ($threshold / 100)));
        $count = 0;

        DB::transaction(function () use ($session, $participants, $requiredMinutes, $actor, &$count) {
            foreach ($participants as $row) {
                $student = $this->resolveStudent($row, $session->offering_id);
                if ($student === null) {
                    continue;
                }

                $minutes = (int) ($row['minutes'] ?? 0);
                $status = $minutes >= $requiredMinutes
                    ? AttendanceStatus::Present
                    : AttendanceStatus::Absent;

                AttendanceRecord::query()->updateOrCreate(
                    [
                        'live_session_id' => $session->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'status' => $status,
                        'minutes_attended' => $minutes,
                        'source' => AttendanceSource::ZoomImport,
                    ]
                );
                $count++;
            }

            $this->audit->write($actor, 'attendance.import', 'LiveSession', $session->id, null, ['count' => $count]);
        });

        return $count;
    }

    public function override(User $actor, LiveSession $session, User $student, AttendanceStatus $status, ?int $minutes = null): AttendanceRecord
    {
        $this->authorize->authorize($actor, 'attendance.manage');

        $record = AttendanceRecord::query()->updateOrCreate(
            [
                'live_session_id' => $session->id,
                'student_id' => $student->id,
            ],
            [
                'status' => $status,
                'minutes_attended' => $minutes ?? ($status === AttendanceStatus::Present ? $session->duration_minutes : 0),
                'source' => AttendanceSource::Manual,
                'overridden_by_id' => $actor->id,
            ]
        );

        $this->audit->write($actor, 'attendance.override', 'AttendanceRecord', $record->id);

        return $record;
    }

    public function attachRecording(LiveSession $session, string $url): void
    {
        $session->update(['recording_url' => $url]);
    }

    /**
     * Attendance % for gradebook ATTENDANCE components (present / total sessions).
     */
    public function offeringPercent(CourseOffering $offering, User $student): ?float
    {
        $sessionIds = LiveSession::query()->where('offering_id', $offering->id)->pluck('id');
        if ($sessionIds->isEmpty()) {
            return null;
        }

        $present = AttendanceRecord::query()
            ->whereIn('live_session_id', $sessionIds)
            ->where('student_id', $student->id)
            ->where('status', AttendanceStatus::Present)
            ->count();

        return round(($present / $sessionIds->count()) * 100, 2);
    }

    private function thresholdPercent(CourseOffering $offering): float
    {
        return (float) ($offering->attendance_threshold_percent
            ?? \App\Models\Setting::query()->find('attendance.default_threshold')?->value['value']
            ?? 60);
    }

    /**
     * @param  array{email?: string, user_id?: string}  $row
     */
    private function resolveStudent(array $row, string $offeringId): ?User
    {
        if (! empty($row['user_id'])) {
            $user = User::query()->find($row['user_id']);
        } elseif (! empty($row['email'])) {
            $user = User::query()->where('email', $row['email'])->first();
        } else {
            return null;
        }

        if ($user === null) {
            return null;
        }

        $enrolled = Enrollment::query()
            ->where('student_id', $user->id)
            ->where('offering_id', $offeringId)
            ->where('status', EnrollmentStatus::Enrolled)
            ->exists();

        return $enrolled ? $user : null;
    }
}
