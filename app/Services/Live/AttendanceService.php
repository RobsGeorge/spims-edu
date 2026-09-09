<?php

namespace App\Services\Live;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Enums\EnrollmentStatus;
use App\Enums\NotificationChannel;
use App\Exceptions\ConflictException;
use App\Exceptions\ResourceLockedException;
use App\Models\AttendanceCheckInCode;
use App\Models\AttendanceEntry;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\Notification;
use App\Models\SessionNotificationTarget;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     scheduled_start: mixed,
     *     duration_minutes: int,
     *     mode?: string,
     *     location?: ?string,
     *     live_session_id?: ?string,
     *     notify_students?: bool
     * }  $data
     */
    public function openSession(User $actor, CourseOffering $offering, array $data): ClassSession
    {
        $this->authorize->authorize($actor, 'attendance.record', $offering);

        return $this->audit->withAudit($actor, 'attendance.session_open', function () use ($offering, $data, $actor) {
            $session = ClassSession::query()->create([
                'offering_id' => $offering->id,
                'title' => $data['title'],
                'scheduled_start' => $data['scheduled_start'],
                'duration_minutes' => (int) $data['duration_minutes'],
                'mode' => ClassSessionMode::from($data['mode'] ?? ClassSessionMode::InPerson->value),
                'location' => $data['location'] ?? null,
                'live_session_id' => $data['live_session_id'] ?? null,
                'notify_students' => (bool) ($data['notify_students'] ?? false),
                'lock_version' => 0,
            ]);

            if ($session->notify_students) {
                $this->recordSessionNotifications($session, $actor);
            }

            return $session;
        }, 'ClassSession');
    }

    /**
     * @param  array<int, array{student_id: string, status: string, minutes_attended?: int, excuse_reason?: ?string}>  $marks
     * @return Collection<int, AttendanceEntry>
     */
    public function markRoster(User $actor, ClassSession $session, array $marks, int $lockVersion): Collection
    {
        $this->authorize->authorize($actor, 'attendance.record', $session);
        $this->assertWritable($session);

        return $this->audit->withAudit($actor, 'attendance.mark_roster', function () use ($session, $marks, $lockVersion, $actor) {
            $locked = ClassSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->lock_version !== $lockVersion) {
                throw new ConflictException(__('attendance.stale_write'));
            }

            $this->assertWritable($locked);
            $this->assertEnrolledStudents($locked->offering_id, collect($marks)->pluck('student_id')->all());

            $entries = collect();
            foreach ($marks as $mark) {
                $entries->push($this->upsertEntry(
                    $locked,
                    $mark['student_id'],
                    AttendanceStatus::from($mark['status']),
                    AttendanceSource::Manual,
                    $actor,
                    isset($mark['minutes_attended']) ? (int) $mark['minutes_attended'] : null,
                    $mark['excuse_reason'] ?? null,
                ));
            }

            $locked->increment('lock_version');
            $session->lock_version = (int) $locked->fresh()->lock_version;

            return $entries;
        }, 'ClassSession');
    }

    public function markOne(
        User $actor,
        ClassSession $session,
        User $student,
        AttendanceStatus $status,
        int $lockVersion,
        ?int $minutes = null,
        ?string $excuseReason = null,
    ): AttendanceEntry {
        $entries = $this->markRoster($actor, $session, [[
            'student_id' => $student->id,
            'status' => $status->value,
            'minutes_attended' => $minutes ?? 0,
            'excuse_reason' => $excuseReason,
        ]], $lockVersion);

        return $entries->first();
    }

    /**
     * @return Collection<int, AttendanceEntry>
     */
    public function fillMissing(User $actor, ClassSession $session): Collection
    {
        $this->authorize->authorize($actor, 'attendance.record', $session);
        $this->assertWritable($session);

        return $this->audit->withAudit($actor, 'attendance.fill_missing', function () use ($session, $actor) {
            $locked = ClassSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertWritable($locked);

            $marked = AttendanceEntry::query()
                ->where('class_session_id', $locked->id)
                ->pluck('student_id');

            $missing = Enrollment::query()
                ->where('offering_id', $locked->offering_id)
                ->where('status', EnrollmentStatus::Enrolled)
                ->whereNotIn('student_id', $marked)
                ->pluck('student_id');

            $entries = collect();
            foreach ($missing as $studentId) {
                $entries->push($this->upsertEntry(
                    $locked,
                    $studentId,
                    AttendanceStatus::Absent,
                    AttendanceSource::Manual,
                    $actor,
                    0,
                    null,
                ));
            }

            if ($entries->isNotEmpty()) {
                $locked->increment('lock_version');
            }

            return $entries;
        }, 'ClassSession');
    }

    public function closeSession(User $actor, ClassSession $session): ClassSession
    {
        $this->authorize->authorize($actor, 'attendance.record', $session);

        return $this->audit->withAudit($actor, 'attendance.session_close', function () use ($session) {
            $session->update(['attendance_closed_at' => now()]);

            return $session->fresh();
        }, 'ClassSession');
    }

    public function reopenSession(User $actor, ClassSession $session): ClassSession
    {
        $this->authorize->authorize($actor, 'attendance.reopen', $session);

        return $this->audit->withAudit($actor, 'attendance.session_reopen', function () use ($session) {
            $session->update(['attendance_closed_at' => null]);

            return $session->fresh();
        }, 'ClassSession');
    }

    public function excuse(User $actor, ClassSession $session, User $student, string $reason, int $lockVersion): AttendanceEntry
    {
        $this->authorize->authorize($actor, 'attendance.edit', $session);
        $this->assertWritable($session);
        $this->assertEnrolled($student->id, $session->offering_id);

        return $this->audit->withAudit($actor, 'attendance.excuse', function () use ($session, $student, $reason, $lockVersion, $actor) {
            $locked = ClassSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->lock_version !== $lockVersion) {
                throw new ConflictException(__('attendance.stale_write'));
            }

            $this->assertWritable($locked);

            $entry = $this->upsertEntry(
                $locked,
                $student->id,
                AttendanceStatus::Excused,
                AttendanceSource::Manual,
                $actor,
                0,
                $reason,
            );

            $locked->increment('lock_version');
            $session->lock_version = (int) $locked->fresh()->lock_version;

            return $entry;
        }, 'AttendanceEntry');
    }

    public function selfCheckIn(User $student, ClassSession $session, string $code): AttendanceEntry
    {
        $this->authorize->authorize($student, 'attendance.self_check_in');

        return $this->audit->withAudit($student, 'attendance.self_check_in', function () use ($student, $session, $code) {
            $this->assertWritable($session);
            $this->assertEnrolled($student->id, $session->offering_id);

            $row = AttendanceCheckInCode::query()
                ->where('class_session_id', $session->id)
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $existsElsewhere = AttendanceCheckInCode::query()->where('code', $code)->exists();
                throw ValidationException::withMessages([
                    'code' => [__($existsElsewhere ? 'attendance.check_in_wrong_session' : 'attendance.check_in_invalid')],
                ]);
            }

            if ($row->isExpired()) {
                throw ValidationException::withMessages([
                    'code' => [__('attendance.check_in_expired')],
                ]);
            }

            if ($row->isExhausted()) {
                throw ValidationException::withMessages([
                    'code' => [__('attendance.check_in_exhausted')],
                ]);
            }

            $already = AttendanceEntry::query()
                ->where('class_session_id', $session->id)
                ->where('student_id', $student->id)
                ->where('source', AttendanceSource::SelfCheckIn)
                ->exists();

            if ($already) {
                throw ValidationException::withMessages([
                    'code' => [__('attendance.check_in_replayed')],
                ]);
            }

            $row->increment('uses');

            $entry = $this->upsertEntry(
                $session,
                $student->id,
                AttendanceStatus::Present,
                AttendanceSource::SelfCheckIn,
                $student,
                $session->duration_minutes,
                null,
            );

            $session->increment('lock_version');

            return $entry;
        }, 'AttendanceEntry');
    }

    /**
     * @param  array{ttl_minutes?: int, max_uses?: ?int}  $data
     */
    public function issueCheckInCode(User $actor, ClassSession $session, array $data = []): AttendanceCheckInCode
    {
        $this->authorize->authorize($actor, 'attendance.record', $session);
        $this->assertWritable($session);

        return $this->audit->withAudit($actor, 'attendance.check_in_code', function () use ($session, $data) {
            return AttendanceCheckInCode::query()->create([
                'class_session_id' => $session->id,
                'code' => strtoupper(Str::random(6)),
                'expires_at' => now()->addMinutes((int) ($data['ttl_minutes'] ?? 30)),
                'max_uses' => $data['max_uses'] ?? null,
                'uses' => 0,
            ]);
        }, 'AttendanceCheckInCode');
    }

    public function percentFor(User $student, CourseOffering $offering): ?float
    {
        $policy = $this->resolvePolicy($offering);

        if ($policy !== null && (! $policy->is_enabled || ! $policy->counts_toward_grade)) {
            return null;
        }

        $sessions = ClassSession::query()->where('offering_id', $offering->id)->get();

        if ($sessions->isEmpty()) {
            return $this->legacyLivePercent($offering, $student);
        }

        $entries = AttendanceEntry::query()
            ->whereIn('class_session_id', $sessions->pluck('id'))
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('class_session_id');

        return $this->percentFromSessions($sessions, $entries, $policy);
    }

    /**
     * Attendance % for every enrolled student on the offering, one session/entry load.
     *
     * @return array<string, float|null>
     */
    public function offeringPercents(CourseOffering $offering): array
    {
        $policy = $this->resolvePolicy($offering);

        if ($policy !== null && (! $policy->is_enabled || ! $policy->counts_toward_grade)) {
            return [];
        }

        $studentIds = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->pluck('student_id');

        $sessions = ClassSession::query()->where('offering_id', $offering->id)->get();

        if ($sessions->isEmpty()) {
            $result = [];
            foreach ($studentIds as $studentId) {
                $student = new User;
                $student->id = $studentId;
                $result[$studentId] = $this->legacyLivePercent($offering, $student);
            }

            return $result;
        }

        $entries = AttendanceEntry::query()
            ->whereIn('class_session_id', $sessions->pluck('id')->all() ?: ['-'])
            ->get()
            ->groupBy('student_id');

        $result = [];
        foreach ($studentIds as $studentId) {
            $studentEntries = collect($entries->get($studentId, collect()))->keyBy('class_session_id');
            $result[$studentId] = $this->percentFromSessions($sessions, $studentEntries, $policy);
        }

        return $result;
    }

    /**
     * Attendance % for gradebook ATTENDANCE components.
     * Delegates to percentFor(); Zoom-only offerings keep the legacy live-session formula.
     */
    public function offeringPercent(CourseOffering $offering, User $student): ?float
    {
        return $this->percentFor($student, $offering);
    }

    /**
     * @param  array{from?: mixed, to?: mixed}  $filters
     * @return array{
     *     students: list<array<string, mixed>>,
     *     sessions: list<array<string, mixed>>,
     *     aggregate: array<string, mixed>
     * }
     */
    public function report(User $actor, CourseOffering $offering, array $filters = []): array
    {
        $this->authorize->authorize($actor, 'attendance.report', $offering);

        $sessions = ClassSession::query()
            ->where('offering_id', $offering->id)
            ->when(isset($filters['from']), fn ($q) => $q->where('scheduled_start', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->where('scheduled_start', '<=', $filters['to']))
            ->orderBy('scheduled_start')
            ->get();

        $students = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter();

        $entries = AttendanceEntry::query()
            ->whereIn('class_session_id', $sessions->pluck('id')->all() ?: ['-'])
            ->get()
            ->groupBy('student_id');

        $studentRows = [];
        foreach ($students as $student) {
            $byStatus = [
                AttendanceStatus::Present->value => 0,
                AttendanceStatus::Absent->value => 0,
                AttendanceStatus::Late->value => 0,
                AttendanceStatus::Excused->value => 0,
            ];
            foreach ($entries->get($student->id, collect()) as $entry) {
                $byStatus[$entry->status->value] = ($byStatus[$entry->status->value] ?? 0) + 1;
            }

            $studentRows[] = [
                'student_id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'present' => $byStatus[AttendanceStatus::Present->value],
                'absent' => $byStatus[AttendanceStatus::Absent->value],
                'late' => $byStatus[AttendanceStatus::Late->value],
                'excused' => $byStatus[AttendanceStatus::Excused->value],
                'percent' => $this->percentFor($student, $offering),
            ];
        }

        $sessionRows = [];
        foreach ($sessions as $session) {
            $counts = AttendanceEntry::query()
                ->where('class_session_id', $session->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $sessionRows[] = [
                'session_id' => $session->id,
                'title' => $session->title,
                'scheduled_start' => $session->scheduled_start?->toIso8601String(),
                'present' => (int) ($counts[AttendanceStatus::Present->value] ?? 0),
                'absent' => (int) ($counts[AttendanceStatus::Absent->value] ?? 0),
                'late' => (int) ($counts[AttendanceStatus::Late->value] ?? 0),
                'excused' => (int) ($counts[AttendanceStatus::Excused->value] ?? 0),
            ];
        }

        return [
            'students' => $studentRows,
            'sessions' => $sessionRows,
            'aggregate' => [
                'session_count' => $sessions->count(),
                'student_count' => count($studentRows),
                'present' => array_sum(array_column($studentRows, 'present')),
                'absent' => array_sum(array_column($studentRows, 'absent')),
                'late' => array_sum(array_column($studentRows, 'late')),
                'excused' => array_sum(array_column($studentRows, 'excused')),
            ],
        ];
    }

    /**
     * @param  array<int, array{email?: string, user_id?: string, minutes: int}>  $participants
     */
    public function importFromZoom(User $actor, LiveSession $session, array $participants): int
    {
        $this->authorize->authorize($actor, 'attendance.manage', $session);

        $threshold = $this->thresholdPercent($session->offering);
        $requiredMinutes = max(1, (int) ceil($session->duration_minutes * ($threshold / 100)));
        $count = 0;

        DB::transaction(function () use ($session, $participants, $requiredMinutes, $actor, &$count) {
            $classSession = $this->classSessionForLive($session);

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
                        'class_session_id' => $classSession->id,
                    ]
                );

                $this->upsertEntry(
                    $classSession,
                    $student->id,
                    $status,
                    AttendanceSource::ZoomImport,
                    $actor,
                    $minutes,
                    null,
                );
                $count++;
            }

            $this->audit->write($actor, 'attendance.import', 'LiveSession', $session->id, null, ['count' => $count]);
        });

        return $count;
    }

    public function override(User $actor, LiveSession $session, User $student, AttendanceStatus $status, ?int $minutes = null): AttendanceRecord
    {
        $this->authorize->authorize($actor, 'attendance.manage', $session);

        $classSession = $this->classSessionForLive($session);

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
                'class_session_id' => $classSession->id,
            ]
        );

        $this->upsertEntry(
            $classSession,
            $student->id,
            $status,
            AttendanceSource::Manual,
            $actor,
            $record->minutes_attended,
            null,
        );

        $this->audit->write($actor, 'attendance.override', 'AttendanceRecord', $record->id);

        return $record;
    }

    public function attachRecording(LiveSession $session, string $url): void
    {
        $session->update(['recording_url' => $url]);
    }

    /**
     * @param  array{
     *     offering_id?: ?string,
     *     min_percentage: int,
     *     late_grade_percentage: int,
     *     counts_toward_grade?: bool,
     *     is_enabled?: bool
     * }  $data
     */
    public function savePolicy(User $actor, array $data): AttendancePolicy
    {
        $offeringId = $data['offering_id'] ?? null;
        $resource = $offeringId !== null ? CourseOffering::query()->findOrFail($offeringId) : null;
        $this->authorize->authorize($actor, 'attendance.configure', $resource);

        return $this->audit->withAudit($actor, 'attendance.policy_save', function () use ($data, $offeringId) {
            $policy = AttendancePolicy::query()->firstOrNew(['offering_id' => $offeringId]);
            $policy->fill([
                'min_percentage' => (int) $data['min_percentage'],
                'late_grade_percentage' => (int) $data['late_grade_percentage'],
                'counts_toward_grade' => (bool) ($data['counts_toward_grade'] ?? true),
                'is_enabled' => (bool) ($data['is_enabled'] ?? true),
            ]);
            $policy->save();

            return $policy;
        }, 'AttendancePolicy');
    }

    public function resolvePolicy(CourseOffering $offering): ?AttendancePolicy
    {
        return AttendancePolicy::query()->where('offering_id', $offering->id)->first()
            ?? AttendancePolicy::query()->whereNull('offering_id')->first();
    }

    /**
     * @return Collection<int, AttendanceEntry>
     */
    public function historyForStudent(User $student, ?CourseOffering $offering = null): Collection
    {
        $this->authorize->authorize($student, 'attendance.view_own');

        $offeringIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->pluck('offering_id');

        $sessionQuery = ClassSession::query()->whereIn('offering_id', $offeringIds);
        if ($offering !== null) {
            $sessionQuery->where('offering_id', $offering->id);
        }

        return AttendanceEntry::query()
            ->where('student_id', $student->id)
            ->whereIn('class_session_id', $sessionQuery->pluck('id')->all() ?: ['-'])
            ->with(['session.offering.course'])
            ->orderByDesc('recorded_at')
            ->get();
    }

    private function classSessionForLive(LiveSession $session): ClassSession
    {
        return ClassSession::query()->firstOrCreate(
            ['live_session_id' => $session->id],
            [
                'offering_id' => $session->offering_id,
                'title' => $session->title,
                'scheduled_start' => $session->scheduled_start,
                'duration_minutes' => $session->duration_minutes,
                'mode' => ClassSessionMode::Online,
                'notify_students' => false,
                'lock_version' => 0,
            ]
        );
    }

    private function upsertEntry(
        ClassSession $session,
        string $studentId,
        AttendanceStatus $status,
        AttendanceSource $source,
        ?User $actor,
        ?int $minutes,
        ?string $excuseReason,
    ): AttendanceEntry {
        $existing = AttendanceEntry::query()
            ->where('class_session_id', $session->id)
            ->where('student_id', $studentId)
            ->first();

        $minutesAttended = $minutes ?? match ($status) {
            AttendanceStatus::Present => $session->duration_minutes,
            AttendanceStatus::Late => (int) floor($session->duration_minutes / 2),
            default => 0,
        };

        if ($existing === null) {
            return AttendanceEntry::query()->create([
                'class_session_id' => $session->id,
                'student_id' => $studentId,
                'status' => $status,
                'excuse_reason' => $excuseReason,
                'minutes_attended' => $minutesAttended,
                'source' => $source,
                'recorded_by_id' => $actor?->id,
                'recorded_at' => now(),
                'lock_version' => 0,
            ]);
        }

        $existing->fill([
            'status' => $status,
            'excuse_reason' => $excuseReason,
            'minutes_attended' => $minutesAttended,
            'source' => $source,
            'recorded_by_id' => $actor?->id,
            'recorded_at' => now(),
            'lock_version' => $existing->lock_version + 1,
        ]);
        $existing->save();

        return $existing;
    }

    private function assertWritable(ClassSession $session): void
    {
        if ($session->isClosed()) {
            throw new ResourceLockedException(__('attendance.session_closed'));
        }
    }

    /**
     * @param  array<int, string>  $studentIds
     */
    private function assertEnrolledStudents(string $offeringId, array $studentIds): void
    {
        $studentIds = array_values(array_unique(array_filter($studentIds)));
        if ($studentIds === []) {
            return;
        }

        $enrolled = Enrollment::query()
            ->where('offering_id', $offeringId)
            ->where('status', EnrollmentStatus::Enrolled)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->all();

        if (count($enrolled) !== count($studentIds)) {
            throw ValidationException::withMessages([
                'marks' => [__('attendance.student_not_enrolled')],
            ]);
        }
    }

    private function assertEnrolled(string $studentId, string $offeringId): void
    {
        $enrolled = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('offering_id', $offeringId)
            ->where('status', EnrollmentStatus::Enrolled)
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'code' => [__('attendance.student_not_enrolled')],
            ]);
        }
    }

    private function recordSessionNotifications(ClassSession $session, User $actor): void
    {
        $studentIds = Enrollment::query()
            ->where('offering_id', $session->offering_id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->pluck('student_id');

        foreach ($studentIds as $studentId) {
            SessionNotificationTarget::query()->firstOrCreate(
                ['class_session_id' => $session->id, 'user_id' => $studentId],
                ['notified_at' => now()]
            );

            Notification::query()->create([
                'user_id' => $studentId,
                'type' => 'attendance.session_scheduled',
                'title' => __('attendance.session_notify_title'),
                'body' => __('attendance.session_notify_body', ['title' => $session->title]),
                'channel' => NotificationChannel::InApp,
                'metadata' => [
                    'class_session_id' => $session->id,
                    'offering_id' => $session->offering_id,
                    'actor_id' => $actor->id,
                ],
            ]);
        }
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     * @param  Collection<string, AttendanceEntry>  $entries  keyed by class_session_id
     */
    private function percentFromSessions(Collection $sessions, Collection $entries, ?AttendancePolicy $policy): ?float
    {
        $lateWeight = $policy !== null ? ((int) $policy->late_grade_percentage) / 100 : 0.0;
        $earned = 0.0;
        $counted = 0;

        foreach ($sessions as $session) {
            $entry = $entries->get($session->id);
            $status = $entry?->status;

            if ($status === AttendanceStatus::Excused) {
                continue;
            }

            $counted++;

            if ($status === AttendanceStatus::Present) {
                $earned += 1.0;
            } elseif ($status === AttendanceStatus::Late) {
                $earned += $lateWeight;
            }
        }

        if ($counted === 0) {
            return null;
        }

        $raw = ($earned / $counted) * 100;

        if ($policy !== null && (int) $policy->min_percentage > 0 && $raw < (int) $policy->min_percentage) {
            return 0.0;
        }

        return round($raw, 2);
    }

    private function legacyLivePercent(CourseOffering $offering, User $student): ?float
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
