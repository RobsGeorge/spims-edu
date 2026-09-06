<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\OfferingClosingStatus;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceEntry;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Completion\OfferingClosingService;
use App\Services\Gradebook\GradebookService;
use App\Services\Live\AttendanceService;
use App\Services\Teach\TeachAccessService;
use App\Support\Api\ConditionalGet;
use App\Support\Api\ConfirmationToken;
use App\Support\Api\IdempotencyStore;
use App\Support\Api\PaginatedEnvelope;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TeachOfferingController extends Controller
{
    public function __construct(
        private readonly TeachAccessService $teachAccess,
        private readonly AuthorizeService $authorize,
        private readonly ConfirmationToken $tokens,
        private readonly IdempotencyStore $idempotency,
        private readonly ConditionalGet $conditional,
        private readonly OfferingClosingService $closing,
        private readonly GradebookService $gradebook,
        private readonly AttendanceService $attendance,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $offerings = $this->teachAccess->offeringsFor($user);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $slice = $offerings->forPage($page, $perPage)->values();
        $counts = $this->attentionCounts($slice->pluck('id'));

        $items = $slice->map(function (CourseOffering $offering) use ($counts) {
            $id = $offering->id;

            return [
                'id' => $id,
                'course_code' => $offering->course?->code,
                'course_title' => $offering->course?->title,
                'mode' => $offering->mode->value,
                'status' => $offering->status->value,
                'semester' => $offering->semester?->name,
                'roster_count' => (int) ($counts['roster'][$id] ?? 0),
                'ungraded_assignment_count' => (int) ($counts['ungraded'][$id] ?? 0),
            ];
        });

        $paginator = new LengthAwarePaginator(
            $items->all(),
            $offerings->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return $this->conditional->json($request, PaginatedEnvelope::from($paginator));
    }

    public function show(Request $request, CourseOffering $offering): JsonResponse
    {
        $user = $request->user();
        $this->authorize->authorize($user, 'offerings.view', $offering);
        $offering->loadMissing(['course', 'semester']);

        $rosterCount = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->count();
        $sessionCount = ClassSession::query()->where('offering_id', $offering->id)->count();
        $ungraded = (int) ($this->attentionCounts(collect([$offering->id]))['ungraded'][$offering->id] ?? 0);

        return response()->json([
            'data' => [
                'id' => $offering->id,
                'course_code' => $offering->course?->code,
                'course_title' => $offering->course?->title,
                'mode' => $offering->mode->value,
                'status' => $offering->status->value,
                'roster_count' => $rosterCount,
                'session_count' => $sessionCount,
                'attendance_percent' => $this->offeringAttendancePercent($offering),
                'ungraded_assignment_count' => $ungraded,
                'gradebook_locked' => $this->gradebookLocked($offering),
                'confirmation' => [
                    'gradebook.lock' => $this->tokens->issue($user, 'gradebook.lock', $offering->id, [
                        __('teach.lock_confirm_body'),
                    ]),
                    'offering.close' => $this->tokens->issue($user, 'offering.close', $offering->id, [
                        __('completion.closed'),
                    ]),
                ],
            ],
        ]);
    }

    public function student(Request $request, CourseOffering $offering, User $student): JsonResponse
    {
        $this->authorize->authorize($request->user(), 'roster.view', $offering);

        $enrollment = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('student_id', $student->id)
            ->first();

        abort_unless($enrollment !== null, 404);

        $grades = in_array($enrollment->status, [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed], true)
            ? $this->gradebook->computeEnrollment($enrollment)
            : null;

        return $this->conditional->json($request, [
            'data' => [
                'profile' => [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'date_of_birth' => $student->date_of_birth?->toDateString(),
                    'preferred_locale' => $student->preferred_locale,
                ],
                'enrollment' => [
                    'id' => $enrollment->id,
                    'status' => $enrollment->status->value,
                    'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
                    'grade_status' => $enrollment->grade_status?->value,
                    'final_percent' => $enrollment->final_percent,
                    'final_letter' => $enrollment->final_letter,
                ],
                'grades' => $grades,
                'attendance_percent' => $this->attendance->percentFor($student, $offering),
            ],
        ]);
    }

    public function close(Request $request, CourseOffering $offering): JsonResponse
    {
        $user = $request->user();
        $this->authorize->authorize($user, 'offering.close', $offering);

        $payload = $this->idempotency->remember(
            $user,
            'teach.offering.close:'.$offering->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $user, $offering) {
                $raw = $request->input('confirmation');
                $this->tokens->consume($user, 'offering.close', $offering->id, is_string($raw) ? $raw : null);
                $record = $this->closing->close($user, $offering);

                return [
                    'status' => $record->status->value,
                    'closed_at' => $record->closed_at?->toIso8601String(),
                ];
            },
        );

        return response()->json(['data' => $payload]);
    }

    /**
     * @param  Collection<int, string>  $offeringIds
     * @return array{roster: Collection<string, int>, ungraded: Collection<string, int>}
     */
    private function attentionCounts(Collection $offeringIds): array
    {
        $ids = $offeringIds->filter()->values();
        if ($ids->isEmpty()) {
            return ['roster' => collect(), 'ungraded' => collect()];
        }

        $roster = Enrollment::query()
            ->whereIn('offering_id', $ids->all())
            ->where('status', EnrollmentStatus::Enrolled)
            ->selectRaw('offering_id, count(*) as c')
            ->groupBy('offering_id')
            ->pluck('c', 'offering_id');

        $ungraded = AssignmentSubmission::query()
            ->whereNull('assignment_submissions.final_score')
            ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
            ->join('content_items', 'content_items.id', '=', 'assignments.content_item_id')
            ->join('weeks', 'weeks.id', '=', 'content_items.week_id')
            ->whereIn('weeks.offering_id', $ids->all())
            ->selectRaw('weeks.offering_id as offering_id, count(*) as c')
            ->groupBy('weeks.offering_id')
            ->pluck('c', 'offering_id');

        return ['roster' => $roster, 'ungraded' => $ungraded];
    }

    private function offeringAttendancePercent(CourseOffering $offering): ?float
    {
        $sessionIds = ClassSession::query()->where('offering_id', $offering->id)->pluck('id');
        if ($sessionIds->isEmpty()) {
            return null;
        }

        $rows = AttendanceEntry::query()
            ->whereIn('class_session_id', $sessionIds->all())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($rows[AttendanceStatus::Present->value] ?? 0);
        $late = (int) ($rows[AttendanceStatus::Late->value] ?? 0);
        $absent = (int) ($rows[AttendanceStatus::Absent->value] ?? 0);
        $counted = $present + $late + $absent;
        if ($counted === 0) {
            return null;
        }

        return round((($present + $late) / $counted) * 100, 2);
    }

    private function gradebookLocked(CourseOffering $offering): bool
    {
        if ($this->closing->statusFor($offering) !== OfferingClosingStatus::Open) {
            return true;
        }

        return Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('grade_status', GradeStatus::Locked)
            ->exists();
    }
}
