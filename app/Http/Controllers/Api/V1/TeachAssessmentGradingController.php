<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\ProctorEvent;
use App\Models\User;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use App\Support\Api\ConfirmationToken;
use App\Support\Api\StudentPayload;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachAssessmentGradingController extends Controller
{
    public function __construct(
        private readonly AttemptService $attempts,
        private readonly AssessmentService $assessments,
        private readonly AuthorizeService $authorize,
        private readonly ConfirmationToken $confirmation,
    ) {}

    public function attempts(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $request->user();
        $this->authorize->authorize($actor, 'assessments.grade', $assessment);

        $rows = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->with(['student', 'answers', 'proctorEvents'])
            ->orderBy('attempt_no')
            ->get()
            ->map(fn (AssessmentAttempt $attempt) => [
                'id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'first_name' => $attempt->student?->first_name,
                'last_name' => $attempt->student?->last_name,
                'attempt_no' => $attempt->attempt_no,
                'status' => $attempt->status->value,
                'total_score' => $attempt->total_score,
                'proctor_warnings' => $attempt->proctor_warnings,
                'terminated_for_cheating' => $attempt->terminated_for_cheating,
                'submitted_at' => StudentPayload::iso($attempt->submitted_at),
                'answers' => $attempt->answers->map(fn (AttemptAnswer $answer) => [
                    'id' => $answer->id,
                    'question_id' => $answer->question_id,
                    'auto_score' => $answer->auto_score,
                    'ai_suggested_score' => $answer->ai_suggested_score,
                    'final_score' => $answer->final_score,
                    'feedback' => $answer->feedback,
                ])->values(),
                'proctor_events' => $attempt->proctorEvents->map(fn (ProctorEvent $event) => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'warning_number' => $event->warning_number,
                    'created_at' => StudentPayload::iso($event->created_at),
                ])->values(),
            ])
            ->values();

        $payload = ['attempts' => $rows];
        $announce = $this->announceConfirmation($actor, $assessment);
        if ($announce !== null) {
            $payload['confirmation'] = $announce;
        }

        return response()->json(['data' => $payload]);
    }

    public function gradeAnswer(Request $request, AttemptAnswer $attemptAnswer): JsonResponse
    {
        $data = $request->validate([
            'final_score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $graded = $this->attempts->overrideScore(
            $request->user(),
            $attemptAnswer,
            (float) $data['final_score'],
            $data['feedback'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $graded->id,
                'question_id' => $graded->question_id,
                'final_score' => $graded->final_score,
                'feedback' => $graded->feedback,
                'graded_at' => StudentPayload::iso($graded->graded_at),
            ],
        ]);
    }

    public function announceResults(Request $request, Assessment $assessment): JsonResponse
    {
        $actor = $request->user();
        $this->authorize->authorize($actor, 'assessments.announce_results', $assessment);

        $data = $request->validate([
            'confirmation' => 'nullable|string',
        ]);

        $this->confirmation->consume(
            $actor,
            'assessments.announce_results',
            $assessment->id,
            $data['confirmation'] ?? null,
        );

        $announcement = $this->assessments->announceResults($actor, $assessment);

        return response()->json([
            'data' => [
                'id' => $announcement->id,
                'assessment_id' => $announcement->assessment_id,
                'announced_at' => StudentPayload::iso($announcement->announced_at),
                'announced_by_id' => $announcement->announced_by_id,
            ],
        ]);
    }

    /** @return array{confirmation_token: string, consequences: array<int, mixed>, expires_at: string}|null */
    private function announceConfirmation(User $actor, Assessment $assessment): ?array
    {
        try {
            $this->authorize->authorize($actor, 'assessments.announce_results', $assessment);
        } catch (AuthorizationException) {
            return null;
        }

        return $this->confirmation->issue(
            $actor,
            'assessments.announce_results',
            $assessment->id,
            [
                __('assessment.results_announced_title'),
                __('assessment.results_announced_body', ['title' => $assessment->title]),
            ],
        );
    }
}
