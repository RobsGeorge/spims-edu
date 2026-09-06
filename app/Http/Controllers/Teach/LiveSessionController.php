<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Teach\Concerns\GuardsTeachOffering;
use App\Models\CourseOffering;
use App\Models\LiveSession;
use App\Services\Live\AttendanceService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LiveSessionController extends Controller
{
    use GuardsTeachOffering;

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AttendanceService $attendance,
    ) {}

    public function index(Request $request, CourseOffering $offering): View
    {
        $this->guardTeach($request, $offering);
        $this->authorize->authorize($request->user(), 'live.schedule', $offering);

        return view('teach.live.index', [
            'offering' => $offering->load('course'),
            'sessions' => LiveSession::query()
                ->where('offering_id', $offering->id)
                ->orderBy('scheduled_start')
                ->with('attendance.student')
                ->get(),
        ]);
    }

    public function importAttendance(
        Request $request,
        CourseOffering $offering,
        LiveSession $liveSession,
    ): RedirectResponse {
        $this->guardTeach($request, $offering);
        abort_unless($liveSession->offering_id === $offering->id, 404);

        $data = validator(
            ['participants' => $this->participantsFromRequest($request)],
            [
                'participants' => 'required|array|min:1',
                'participants.*.email' => 'nullable|email',
                'participants.*.user_id' => 'nullable|string',
                'participants.*.minutes' => 'required|integer|min:0',
            ],
        )->validate();

        $count = $this->attendance->importFromZoom($request->user(), $liveSession, $data['participants']);

        return back()->with('status', __('live.attendance_imported', ['count' => $count]));
    }

    /**
     * Accept pasted Zoom JSON, CSV-style rows, or named participant fields.
     *
     * @return array<int, array<string, mixed>>
     */
    private function participantsFromRequest(Request $request): array
    {
        $json = trim((string) $request->input('participants_json', ''));
        if ($json !== '') {
            return $this->participantsFromJson($json);
        }

        $rowsText = trim((string) $request->input('participants_rows', ''));
        if ($rowsText !== '') {
            return $this->participantsFromRows($rowsText);
        }

        $rows = $request->input('participants', []);
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, function ($row) {
            if (! is_array($row)) {
                return false;
            }

            $email = trim((string) ($row['email'] ?? ''));
            $userId = trim((string) ($row['user_id'] ?? ''));
            $minutes = $row['minutes'] ?? null;

            return $email !== '' || $userId !== '' || ($minutes !== null && $minutes !== '');
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participantsFromJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'participants_json' => __('live.participants_json_invalid'),
            ]);
        }

        if (isset($decoded['participants']) && is_array($decoded['participants'])) {
            $decoded = $decoded['participants'];
        } elseif (! array_is_list($decoded)) {
            $decoded = [$decoded];
        }

        return array_values($decoded);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participantsFromRows(string $text): array
    {
        $participants = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));
            $identity = $parts[0] ?? '';
            $minutes = $parts[1] ?? '';
            if ($identity === '') {
                continue;
            }

            $row = ['minutes' => $minutes];
            if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
                $row['email'] = $identity;
            } else {
                $row['user_id'] = $identity;
            }

            $participants[] = $row;
        }

        return $participants;
    }
}
