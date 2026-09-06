<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\CourseOffering;
use App\Services\Assessment\AttemptService;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly StudentRecordGuard $guard,
        private readonly AttemptService $attempts,
    ) {}

    public function index(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->guard->enrollmentForRead($request->user(), $offering);
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);

        $page = Assessment::query()
            ->where('offering_id', $offering->id)
            ->where('released', true)
            ->orderBy('title')
            ->paginate($perPage);

        $userId = $request->user()->id;
        $page->setCollection($page->getCollection()->map(
            fn (Assessment $assessment) => $this->payload($assessment, $userId, detail: false)
        ));

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function show(Request $request, Assessment $assessment): JsonResponse
    {
        $this->guard->enrollmentForRead($request->user(), $assessment->offering);
        if (! $assessment->released) {
            abort(404);
        }

        return response()->json([
            'data' => $this->payload($assessment, $request->user()->id, detail: true),
        ]);
    }

    public function start(Request $request, Assessment $assessment): JsonResponse
    {
        $this->guard->enrollmentForWrite($request->user(), $assessment->offering);
        $attempt = $this->attempts->start($request->user(), $assessment);

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->id,
                'due_at' => StudentPayload::iso($attempt->due_at),
                'questions' => $attempt->exam_snapshot ?? [],
                'attempt_no' => $attempt->attempt_no,
                'status' => $attempt->status->value,
            ],
        ], 201);
    }

    /** @return array<string, mixed> */
    private function payload(Assessment $assessment, string $userId, bool $detail): array
    {
        $used = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $userId)
            ->count();

        $data = [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'mode' => $assessment->mode->value,
            'time_limit_minutes' => $assessment->time_limit_minutes,
            'opens_at' => StudentPayload::iso($assessment->opens_at),
            'closes_at' => StudentPayload::iso($assessment->closes_at),
            'attempts_allowed' => $assessment->attempts_allowed,
            'attempts_used' => $used,
            'max_points' => $assessment->max_points,
            'released' => $assessment->released,
        ];

        if ($detail) {
            $data['scoring_rule'] = $assessment->scoring_rule->value;
            $data['results_visibility'] = $assessment->results_visibility->value;
            $data['enforce_full_screen'] = $assessment->enforce_full_screen;
            $data['one_at_a_time'] = $assessment->one_at_a_time;
            $data['no_backtrack'] = $assessment->no_backtrack;
            $data['log_focus_loss'] = $assessment->log_focus_loss;
            $data['window_open'] = $assessment->isOpen();
        }

        return $data;
    }
}
