<?php

namespace App\Http\Controllers\Teach;

use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Exceptions\ConflictException;
use App\Exceptions\ResourceLockedException;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Attendance\RosterService;
use App\Services\Live\AttendanceService;
use App\Services\Teach\TeachAccessService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly TeachAccessService $teachAccess,
        private readonly AttendanceService $attendance,
        private readonly RosterService $roster,
        private readonly AuthorizeService $authorize,
    ) {}

    public function index(Request $request, CourseOffering $offering): View
    {
        $this->guardTeach($request, $offering);
        $this->authorize->authorize($request->user(), 'attendance.view_all', $offering);

        $offering->load('course');
        $tab = $request->query('tab', 'sessions');
        $sessions = ClassSession::query()
            ->where('offering_id', $offering->id)
            ->orderByDesc('scheduled_start')
            ->get();

        $report = $tab === 'report'
            ? $this->attendance->report($request->user(), $offering)
            : null;

        $birthdays = $tab === 'roster'
            ? $this->roster->birthdays($request->user(), $offering)
            : collect();

        return view('teach.attendance.index', [
            'offering' => $offering,
            'tab' => $tab,
            'sessions' => $sessions,
            'modes' => ClassSessionMode::cases(),
            'report' => $report,
            'birthdays' => $birthdays,
            'rosterCount' => Enrollment::query()->where('offering_id', $offering->id)->count(),
        ]);
    }

    public function store(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'scheduled_start' => 'required|date',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'mode' => 'required|in:IN_PERSON,ONLINE,HYBRID',
            'location' => 'nullable|string|max:200',
            'notify_students' => 'nullable|boolean',
        ]);

        $this->attendance->openSession($request->user(), $offering, [
            ...$data,
            'notify_students' => (bool) ($data['notify_students'] ?? false),
        ]);

        return redirect()
            ->route('teach.attendance.index', $offering)
            ->with('status', __('attendance.session_opened'));
    }

    public function show(Request $request, CourseOffering $offering, ClassSession $session): View
    {
        $this->guardTeach($request, $offering);
        abort_unless($session->offering_id === $offering->id, 404);
        $this->authorize->authorize($request->user(), 'attendance.view_all', $session);

        $search = trim((string) $request->query('q', ''));
        $enrollments = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->with('student')
            ->get();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $enrollments = $enrollments->filter(function ($enrollment) use ($needle) {
                $student = $enrollment->student;
                $hay = mb_strtolower(trim(($student?->first_name ?? '').' '.($student?->last_name ?? '').' '.($student?->email ?? '')));

                return str_contains($hay, $needle);
            });
        }

        $marks = $session->entries()->get()->keyBy('student_id');

        return view('teach.attendance.grid', [
            'offering' => $offering->load('course'),
            'session' => $session,
            'enrollments' => $enrollments->values(),
            'marks' => $marks,
            'search' => $search,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function mark(Request $request, CourseOffering $offering, ClassSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        abort_unless($session->offering_id === $offering->id, 404);

        $data = $request->validate([
            'lock_version' => 'required|integer|min:0',
            'marks' => 'required|array|min:1',
            'marks.*.student_id' => 'required|string|exists:users,id',
            'marks.*.status' => 'required|in:PRESENT,ABSENT,LATE,EXCUSED',
            'marks.*.minutes_attended' => 'nullable|integer|min:0',
            'marks.*.excuse_reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->attendance->markRoster($request->user(), $session, $data['marks'], (int) $data['lock_version']);
        } catch (ConflictException $e) {
            return back()->withErrors(['lock_version' => $e->getMessage()]);
        } catch (ResourceLockedException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('status', __('attendance.roster_saved'));
    }

    public function fillMissing(Request $request, CourseOffering $offering, ClassSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        abort_unless($session->offering_id === $offering->id, 404);

        try {
            $this->attendance->fillMissing($request->user(), $session);
        } catch (ResourceLockedException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('status', __('attendance.filled_missing'));
    }

    public function close(Request $request, CourseOffering $offering, ClassSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        abort_unless($session->offering_id === $offering->id, 404);

        $this->attendance->closeSession($request->user(), $session);

        return back()->with('status', __('attendance.session_closed_flash'));
    }

    public function reopen(Request $request, CourseOffering $offering, ClassSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        abort_unless($session->offering_id === $offering->id, 404);

        $this->attendance->reopenSession($request->user(), $session);

        return back()->with('status', __('attendance.session_reopened'));
    }

    public function excuse(Request $request, CourseOffering $offering, ClassSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        abort_unless($session->offering_id === $offering->id, 404);

        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
            'excuse_reason' => 'required|string|max:500',
            'lock_version' => 'required|integer|min:0',
        ]);

        try {
            $this->attendance->excuse(
                $request->user(),
                $session,
                User::query()->findOrFail($data['student_id']),
                $data['excuse_reason'],
                (int) $data['lock_version'],
            );
        } catch (ConflictException $e) {
            return back()->withErrors(['lock_version' => $e->getMessage()]);
        } catch (ResourceLockedException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('status', __('attendance.excused'));
    }

    public function issueCode(Request $request, CourseOffering $offering, ClassSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        abort_unless($session->offering_id === $offering->id, 404);

        $data = $request->validate([
            'ttl_minutes' => 'nullable|integer|min:5|max:240',
            'max_uses' => 'nullable|integer|min:1',
        ]);

        try {
            $code = $this->attendance->issueCheckInCode($request->user(), $session, $data);
        } catch (ResourceLockedException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('status', __('attendance.code_issued', ['code' => $code->code]));
    }

    public function reportCsv(Request $request, CourseOffering $offering): StreamedResponse
    {
        $this->guardTeach($request, $offering);
        $report = $this->attendance->report($request->user(), $offering);

        return $this->csvDownload(
            'attendance-'.$offering->id.'.csv',
            ['student_id', 'first_name', 'last_name', 'email', 'present', 'absent', 'late', 'excused', 'percent'],
            $report['students'],
        );
    }

    public function rosterCsv(Request $request, CourseOffering $offering): Response
    {
        $this->guardTeach($request, $offering);
        $csv = $this->roster->exportCsv($request->user(), $offering);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="roster-'.$offering->id.'.csv"',
        ]);
    }

    public function announce(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:10000',
        ]);

        $this->roster->announce($request->user(), $offering, $data);

        return redirect()
            ->route('teach.attendance.index', ['offering' => $offering, 'tab' => 'roster'])
            ->with('status', __('attendance.announcement_saved'));
    }

    private function guardTeach(Request $request, CourseOffering $offering): void
    {
        $user = $request->user();
        abort_unless($this->teachAccess->canTeach($user), 403);
        $this->teachAccess->assertCanTeachOffering($user, $offering);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function csvDownload(string $filename, array $headers, array $rows): StreamedResponse
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
