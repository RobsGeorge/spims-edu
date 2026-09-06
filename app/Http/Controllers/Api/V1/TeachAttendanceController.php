<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClassSessionMode;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Services\Attendance\RosterService;
use App\Services\Live\AttendanceService;
use App\Support\Api\ConditionalGet;
use App\Support\Api\IdempotencyStore;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachAttendanceController extends Controller
{
    public function sessions(Request $request, CourseOffering $offering, AuthorizeService $authorize, ConditionalGet $conditional): JsonResponse
    {
        $authorize->authorize($request->user(), 'attendance.view_all', $offering);

        $sessions = ClassSession::query()
            ->where('offering_id', $offering->id)
            ->orderByDesc('scheduled_start')
            ->get();

        return $conditional->json($request, [
            'data' => $sessions->map(fn (ClassSession $session) => $this->sessionPayload($session))->values()->all(),
        ]);
    }

    public function storeSession(Request $request, CourseOffering $offering, AttendanceService $attendance, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'scheduled_start' => 'required|date',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'mode' => 'nullable|in:IN_PERSON,ONLINE,HYBRID',
            'location' => 'nullable|string|max:200',
            'notify_students' => 'nullable|boolean',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.sessions.store:'.$offering->id,
            $request->header('Idempotency-Key'),
            fn () => $this->sessionPayload($attendance->openSession($request->user(), $offering, [
                ...$data,
                'mode' => $data['mode'] ?? ClassSessionMode::InPerson->value,
                'notify_students' => (bool) ($data['notify_students'] ?? false),
            ])),
        );

        return response()->json(['data' => $payload], 201);
    }

    public function roster(Request $request, ClassSession $session, AuthorizeService $authorize): JsonResponse
    {
        $authorize->authorize($request->user(), 'attendance.view_all', $session);

        $marks = $session->entries()->get()->keyBy('student_id');
        $students = Enrollment::query()
            ->where('offering_id', $session->offering_id)
            ->with('student')
            ->orderBy('enrolled_at')
            ->get()
            ->map(function ($enrollment) use ($marks) {
                $mark = $marks->get($enrollment->student_id);

                return [
                    'student_id' => $enrollment->student_id,
                    'first_name' => $enrollment->student?->first_name,
                    'last_name' => $enrollment->student?->last_name,
                    'email' => $enrollment->student?->email,
                    'status' => $mark?->status->value,
                    'minutes_attended' => $mark?->minutes_attended,
                    'lock_version' => $mark?->lock_version,
                ];
            });

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'lock_version' => $session->lock_version,
                'closed' => $session->isClosed(),
                'students' => $students,
            ],
        ]);
    }

    public function mark(Request $request, ClassSession $session, AttendanceService $attendance, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate([
            'lock_version' => 'required|integer|min:0',
            'marks' => 'required|array|min:1',
            'marks.*.student_id' => 'required|string|exists:users,id',
            'marks.*.status' => 'required|in:PRESENT,ABSENT,LATE,EXCUSED',
            'marks.*.minutes_attended' => 'nullable|integer|min:0',
            'marks.*.excuse_reason' => 'nullable|string|max:500',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.attendance.mark:'.$session->id,
            $request->header('Idempotency-Key'),
            function () use ($attendance, $request, $session, $data) {
                $attendance->markRoster($request->user(), $session, $data['marks'], (int) $data['lock_version']);
                $session->refresh();

                return ['lock_version' => $session->lock_version];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function fillMissing(Request $request, ClassSession $session, AttendanceService $attendance, IdempotencyStore $idempotency): JsonResponse
    {
        $payload = $idempotency->remember(
            $request->user(),
            'teach.attendance.fill:'.$session->id,
            $request->header('Idempotency-Key'),
            function () use ($attendance, $request, $session) {
                $entries = $attendance->fillMissing($request->user(), $session);

                return [
                    'filled' => $entries->count(),
                    'lock_version' => $session->fresh()->lock_version,
                ];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function close(Request $request, ClassSession $session, AttendanceService $attendance, IdempotencyStore $idempotency): JsonResponse
    {
        $payload = $idempotency->remember(
            $request->user(),
            'teach.attendance.close:'.$session->id,
            $request->header('Idempotency-Key'),
            function () use ($attendance, $request, $session) {
                $closed = $attendance->closeSession($request->user(), $session);

                return [
                    'attendance_closed_at' => $closed->attendance_closed_at?->toIso8601String(),
                ];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function issueCheckInCode(Request $request, ClassSession $session, AttendanceService $attendance, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate([
            'ttl_minutes' => 'nullable|integer|min:5|max:240',
            'max_uses' => 'nullable|integer|min:1',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.sessions.check-in-code:'.$session->id,
            $request->header('Idempotency-Key'),
            function () use ($attendance, $request, $session, $data) {
                $code = $attendance->issueCheckInCode($request->user(), $session, $data);

                return [
                    'code' => $code->code,
                    'expires_at' => $code->expires_at->toIso8601String(),
                    'max_uses' => $code->max_uses,
                ];
            },
        );

        return response()->json(['data' => $payload], 201);
    }

    public function report(Request $request, CourseOffering $offering, AttendanceService $attendance): JsonResponse|StreamedResponse
    {
        $report = $attendance->report($request->user(), $offering);

        if ($request->query('format') === 'csv') {
            return $this->csv('attendance-'.$offering->id.'.csv', [
                'student_id', 'first_name', 'last_name', 'email', 'present', 'absent', 'late', 'excused', 'percent',
            ], $report['students']);
        }

        return response()->json(['data' => $report]);
    }

    public function offeringRoster(Request $request, CourseOffering $offering, RosterService $roster): JsonResponse|StreamedResponse
    {
        if ($request->query('format') === 'csv') {
            $csv = $roster->exportCsv($request->user(), $offering);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="roster-'.$offering->id.'.csv"',
            ]);
        }

        $rows = $roster->roster($request->user(), $offering)->map(fn ($enrollment) => [
            'student_id' => $enrollment->student_id,
            'first_name' => $enrollment->student?->first_name,
            'last_name' => $enrollment->student?->last_name,
            'email' => $enrollment->student?->email,
            'status' => $enrollment->status->value,
            'date_of_birth' => $enrollment->student?->date_of_birth?->toDateString(),
        ]);

        return response()->json(['data' => $rows]);
    }

    public function birthdays(Request $request, CourseOffering $offering, RosterService $roster): JsonResponse
    {
        $window = (int) $request->query('days', 14);
        $rows = $roster->birthdays($request->user(), $offering, $window)->map(fn (array $row) => [
            'student_id' => $row['student']->id,
            'first_name' => $row['student']->first_name,
            'last_name' => $row['student']->last_name,
            'date_of_birth' => $row['date_of_birth'],
            'next_birthday' => $row['next_birthday'],
            'days_until' => $row['days_until'],
        ]);

        return response()->json(['data' => $rows]);
    }

    /** @return array<string, mixed> */
    private function sessionPayload(ClassSession $session): array
    {
        return [
            'id' => $session->id,
            'offering_id' => $session->offering_id,
            'title' => $session->title,
            'scheduled_start' => $session->scheduled_start?->toIso8601String(),
            'duration_minutes' => $session->duration_minutes,
            'mode' => $session->mode->value,
            'location' => $session->location,
            'attendance_closed_at' => $session->attendance_closed_at?->toIso8601String(),
            'lock_version' => $session->lock_version,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function csv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($key) => $row[$key] ?? '', $headers));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
