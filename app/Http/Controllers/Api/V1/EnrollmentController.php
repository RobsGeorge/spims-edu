<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Services\Enrollment\EnrollmentService;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly StudentRecordGuard $guard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $page = Enrollment::query()
            ->where('student_id', $request->user()->id)
            ->with(['offering.course', 'offering.semester'])
            ->latest('enrolled_at')
            ->paginate($perPage);

        $page->setCollection($page->getCollection()->map(fn (Enrollment $enrollment) => $this->payload($enrollment)));

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offering_id' => 'required|exists:course_offerings,id',
            'student_program_id' => 'nullable|exists:student_programs,id',
        ]);

        $offering = CourseOffering::query()->findOrFail($data['offering_id']);
        $conflict = $this->enrollments->registrationConflict($request->user(), $offering);
        if ($conflict !== null) {
            $message = match ($conflict) {
                'hold' => __('enrollment.financial_hold'),
                'window' => __('enrollment.window_closed'),
                default => __('enrollment.schedule_conflict_warning'),
            };
            throw new ConflictHttpException($message);
        }

        $enrollment = $this->enrollments->register(
            $request->user(),
            $offering,
            $data['student_program_id'] ?? null
        );

        return response()->json(['data' => $this->payload($enrollment->load(['offering.course', 'offering.semester']))], 201);
    }

    public function drop(Request $request, Enrollment $enrollment): JsonResponse
    {
        $this->guard->ownWrite($request->user(), $enrollment->student_id);
        $dropped = $this->enrollments->drop($request->user(), $enrollment);

        return response()->json(['data' => $this->payload($dropped->load(['offering.course']))]);
    }

    public function withdraw(Request $request, Enrollment $enrollment): JsonResponse
    {
        $this->guard->ownWrite($request->user(), $enrollment->student_id);
        $withdrawn = $this->enrollments->withdraw($request->user(), $enrollment);

        return response()->json(['data' => $this->payload($withdrawn->load(['offering.course']))]);
    }

    /** @return array<string, mixed> */
    private function payload(Enrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'offering_id' => $enrollment->offering_id,
            'course_code' => $enrollment->offering?->course?->code,
            'course_title' => $enrollment->offering?->course?->title,
            'status' => $enrollment->status->value,
            'enrolled_at' => StudentPayload::iso($enrollment->enrolled_at),
            'dropped_at' => StudentPayload::iso($enrollment->dropped_at),
            'progress_percent' => $enrollment->progress_percent,
        ];
    }
}
