<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectGrade;
use App\Models\User;
use App\Services\Projects\PeerEvaluationService;
use App\Services\Projects\ProjectDeliverableService;
use App\Services\Projects\ProjectGradingService;
use App\Services\Projects\ProjectTeamService;
use App\Support\Api\ConditionalGet;
use App\Support\Api\ConfirmationToken;
use App\Support\Api\IdempotencyStore;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachProjectController extends Controller
{
    public function index(Request $request, CourseOffering $offering, AuthorizeService $authorize, ConditionalGet $conditional): JsonResponse
    {
        $authorize->authorize($request->user(), 'projects.view', $offering);

        $assessments = ProjectAssessment::query()
            ->where('offering_id', $offering->id)
            ->orderBy('title')
            ->get();

        return $conditional->json($request, [
            'data' => $assessments->map(fn (ProjectAssessment $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'status' => $a->status->value,
                'grading_mode' => $a->grading_mode->value,
                'team_size_min' => $a->team_size_min,
                'team_size_max' => $a->team_size_max,
            ])->values()->all(),
        ]);
    }

    public function teams(
        Request $request,
        ProjectAssessment $projectAssessment,
        AuthorizeService $authorize,
        ProjectTeamService $teams,
        ConfirmationToken $confirmation,
    ): JsonResponse {
        $authorize->authorize($request->user(), 'projects.view', $projectAssessment);

        return response()->json([
            'data' => collect($teams->seating($request->user(), $projectAssessment))->map(fn (array $row) => [
                'id' => $row['project']->id,
                'name' => $row['project']->name,
                'status' => $row['project']->status->value,
                'seats' => $row['seats'],
                'capacity' => $row['capacity'],
            ])->values()->all(),
            'confirmation' => $confirmation->issue(
                $request->user(),
                'projects.announce',
                $projectAssessment->id,
                [
                    __('projects.announce_confirm_title'),
                    __('projects.announce_confirm_body'),
                ],
            ),
        ]);
    }

    public function announce(
        Request $request,
        ProjectAssessment $projectAssessment,
        ProjectGradingService $grading,
        ConfirmationToken $confirmation,
        AuthorizeService $authorize,
        IdempotencyStore $idempotency,
    ): JsonResponse {
        $authorize->authorize($request->user(), 'projects.announce', $projectAssessment);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.projects.announce:'.$projectAssessment->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $projectAssessment, $grading, $confirmation) {
                $raw = $request->input('confirmation');
                $confirmation->consume(
                    $request->user(),
                    'projects.announce',
                    $projectAssessment->id,
                    is_string($raw) ? $raw : null,
                );

                return ['announced' => $grading->announce($request->user(), $projectAssessment)];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function moveMember(
        Request $request,
        Project $project,
        ProjectTeamService $teams,
        IdempotencyStore $idempotency,
    ): JsonResponse {
        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
            'to_project_id' => 'required|string|exists:projects,id',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.projects.move:'.$project->id.':'.$data['student_id'],
            $request->header('Idempotency-Key'),
            function () use ($request, $project, $teams, $data) {
                $student = User::query()->findOrFail($data['student_id']);
                $to = Project::query()->findOrFail($data['to_project_id']);
                $membership = $teams->move($request->user(), $student, $project, $to);

                return [
                    'id' => $membership->id,
                    'project_id' => $membership->project_id,
                    'student_id' => $membership->student_id,
                    'role' => $membership->role->value,
                    'joined_at' => $membership->joined_at?->toIso8601String(),
                ];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function reviewSubmission(
        Request $request,
        ProjectDeliverableSubmission $projectDeliverableSubmission,
        ProjectDeliverableService $deliverables,
        IdempotencyStore $idempotency,
    ): JsonResponse {
        $data = $request->validate([
            'review_status' => 'required|in:PENDING,ACCEPTED,REJECTED,NEEDS_REVISION',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.projects.review:'.$projectDeliverableSubmission->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $projectDeliverableSubmission, $deliverables, $data) {
                $submission = $deliverables->review($request->user(), $projectDeliverableSubmission, $data);

                return [
                    'id' => $submission->id,
                    'project_id' => $submission->project_id,
                    'deliverable_id' => $submission->deliverable_id,
                    'review_status' => $submission->review_status->value,
                    'reviewed_at' => $submission->reviewed_at?->toIso8601String(),
                    'reviewer_id' => $submission->reviewer_id,
                ];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function grade(
        Request $request,
        Project $project,
        ProjectGradingService $grading,
        IdempotencyStore $idempotency,
    ): JsonResponse {
        $data = $request->validate([
            'team_score' => 'nullable|numeric|min:0',
            'students' => 'nullable|array',
            'students.*.student_id' => 'required|string|exists:users,id',
            'students.*.score' => 'required|numeric|min:0',
            'criterion_id' => 'nullable|string|exists:project_grade_criteria,id',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.projects.grade:'.$project->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $project, $grading, $data) {
                $result = $grading->recordScores($request->user(), $project, $data);

                return [
                    'team' => $result['team'] === null ? null : $this->gradePayload($result['team']),
                    'students' => array_map(fn (ProjectGrade $grade) => $this->gradePayload($grade), $result['students']),
                ];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function peerAggregates(
        Request $request,
        Project $project,
        PeerEvaluationService $peers,
        AuthorizeService $authorize,
        ConditionalGet $conditional,
    ): JsonResponse {
        $project->loadMissing('assessment');
        $authorize->authorize($request->user(), 'projects.view', $project);

        return $conditional->json($request, [
            'data' => $peers->aggregatesForStaff($request->user(), $project->assessment),
        ]);
    }

    /** @return array<string, mixed> */
    private function gradePayload(ProjectGrade $grade): array
    {
        return [
            'id' => $grade->id,
            'project_id' => $grade->project_id,
            'student_id' => $grade->student_id,
            'criterion_id' => $grade->criterion_id,
            'score' => $grade->score,
            'announced_at' => $grade->announced_at?->toIso8601String(),
        ];
    }
}
