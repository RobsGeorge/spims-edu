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
use App\Support\Api\ConfirmationToken;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachProjectController extends Controller
{
    public function index(Request $request, CourseOffering $offering, AuthorizeService $authorize): JsonResponse
    {
        $authorize->authorize($request->user(), 'projects.view', $offering);

        $assessments = ProjectAssessment::query()
            ->where('offering_id', $offering->id)
            ->orderBy('title')
            ->get();

        return response()->json([
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
    ): JsonResponse {
        $authorize->authorize($request->user(), 'projects.announce', $projectAssessment);

        $raw = $request->input('confirmation');
        $confirmation->consume(
            $request->user(),
            'projects.announce',
            $projectAssessment->id,
            is_string($raw) ? $raw : null,
        );

        $count = $grading->announce($request->user(), $projectAssessment);

        return response()->json(['data' => ['announced' => $count]]);
    }

    public function moveMember(
        Request $request,
        Project $project,
        ProjectTeamService $teams,
    ): JsonResponse {
        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
            'to_project_id' => 'required|string|exists:projects,id',
        ]);

        $student = User::query()->findOrFail($data['student_id']);
        $to = Project::query()->findOrFail($data['to_project_id']);
        $membership = $teams->move($request->user(), $student, $project, $to);

        return response()->json([
            'data' => [
                'id' => $membership->id,
                'project_id' => $membership->project_id,
                'student_id' => $membership->student_id,
                'role' => $membership->role->value,
                'joined_at' => $membership->joined_at?->toIso8601String(),
            ],
        ]);
    }

    public function reviewSubmission(
        Request $request,
        ProjectDeliverableSubmission $projectDeliverableSubmission,
        ProjectDeliverableService $deliverables,
    ): JsonResponse {
        $data = $request->validate([
            'review_status' => 'required|in:PENDING,ACCEPTED,REJECTED,NEEDS_REVISION',
        ]);

        $submission = $deliverables->review($request->user(), $projectDeliverableSubmission, $data);

        return response()->json([
            'data' => [
                'id' => $submission->id,
                'project_id' => $submission->project_id,
                'deliverable_id' => $submission->deliverable_id,
                'review_status' => $submission->review_status->value,
                'reviewed_at' => $submission->reviewed_at?->toIso8601String(),
                'reviewer_id' => $submission->reviewer_id,
            ],
        ]);
    }

    public function grade(
        Request $request,
        Project $project,
        ProjectGradingService $grading,
    ): JsonResponse {
        $data = $request->validate([
            'team_score' => 'nullable|numeric|min:0',
            'students' => 'nullable|array',
            'students.*.student_id' => 'required|string|exists:users,id',
            'students.*.score' => 'required|numeric|min:0',
            'criterion_id' => 'nullable|string|exists:project_grade_criteria,id',
        ]);

        $result = $grading->recordScores($request->user(), $project, $data);

        return response()->json([
            'data' => [
                'team' => $result['team'] === null ? null : $this->gradePayload($result['team']),
                'students' => array_map(fn (ProjectGrade $grade) => $this->gradePayload($grade), $result['students']),
            ],
        ]);
    }

    public function peerAggregates(
        Request $request,
        Project $project,
        PeerEvaluationService $peers,
        AuthorizeService $authorize,
    ): JsonResponse {
        $project->loadMissing('assessment');
        $authorize->authorize($request->user(), 'projects.view', $project);

        return response()->json([
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
