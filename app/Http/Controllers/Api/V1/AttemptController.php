<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Services\Assessment\AttemptService;
use App\Support\Api\IdempotencyStore;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttemptController extends Controller
{
    public function __construct(
        private readonly StudentRecordGuard $guard,
        private readonly AttemptService $attempts,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function show(Request $request, AssessmentAttempt $attempt): JsonResponse
    {
        $this->guard->ownRead($request->user(), $attempt->student_id);

        return response()->json(['data' => $this->payload($attempt->load('answers'))]);
    }

    public function save(Request $request, AssessmentAttempt $attempt): JsonResponse
    {
        $this->guard->ownWrite($request->user(), $attempt->student_id);
        $data = $request->validate([
            'answers' => 'required|array',
        ]);

        $saved = $this->attempts->autosave($request->user(), $attempt, $data['answers']);

        return response()->json(['data' => $this->payload($saved)]);
    }

    public function submit(Request $request, AssessmentAttempt $attempt): JsonResponse
    {
        $this->guard->ownWrite($request->user(), $attempt->student_id);

        $payload = $this->idempotency->remember(
            $request->user(),
            'attempts.submit:'.$attempt->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $attempt) {
                if ($request->filled('answers') && is_array($request->input('answers'))) {
                    $this->attempts->autosave($request->user(), $attempt, $request->input('answers'));
                }

                $submitted = $this->attempts->submit($request->user(), $attempt->fresh());

                return $this->payload($submitted);
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function timer(Request $request, AssessmentAttempt $attempt): JsonResponse
    {
        $this->guard->ownRead($request->user(), $attempt->student_id);

        return response()->json([
            'data' => [
                'server_now' => now()->utc()->toIso8601String(),
                'due_at' => StudentPayload::iso($attempt->due_at),
                'remaining_seconds' => max(0, now()->diffInSeconds($attempt->due_at, false)),
                'expired' => $attempt->isExpired() || $attempt->status !== AttemptStatus::InProgress,
                'status' => $attempt->status->value,
            ],
        ]);
    }

    public function focusLoss(Request $request, AssessmentAttempt $attempt): JsonResponse
    {
        $this->guard->ownWrite($request->user(), $attempt->student_id);
        $updated = $this->attempts->logFocusLoss($request->user(), $attempt);

        if ($updated->terminated_for_cheating) {
            throw new HttpException(423, __('assessment.terminated_for_cheating'));
        }

        return response()->json([
            'data' => [
                'focus_loss_count' => $updated->focus_loss_count,
                'proctor_warnings' => $updated->proctor_warnings,
                'terminated_for_cheating' => $updated->terminated_for_cheating,
                'status' => $updated->status->value,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(AssessmentAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'assessment_id' => $attempt->assessment_id,
            'attempt_no' => $attempt->attempt_no,
            'status' => $attempt->status->value,
            'started_at' => StudentPayload::iso($attempt->started_at),
            'due_at' => StudentPayload::iso($attempt->due_at),
            'submitted_at' => StudentPayload::iso($attempt->submitted_at),
            'total_score' => $attempt->total_score,
            'questions' => $attempt->exam_snapshot ?? [],
            'answers' => $attempt->answers->map(fn ($answer) => [
                'question_id' => $answer->question_id,
                'response' => $answer->response,
                'final_score' => $answer->final_score,
            ])->values(),
            'remaining_seconds' => $attempt->due_at
                ? max(0, now()->diffInSeconds($attempt->due_at, false))
                : null,
            'terminated_for_cheating' => (bool) $attempt->terminated_for_cheating,
        ];
    }
}
