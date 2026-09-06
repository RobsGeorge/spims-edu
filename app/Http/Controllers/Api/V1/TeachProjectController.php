<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Services\Projects\PeerEvaluationService;
use App\Services\Projects\ProjectGradingService;
use App\Services\Projects\ProjectTeamService;
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

    public function teams(Request $request, ProjectAssessment $projectAssessment, AuthorizeService $authorize, ProjectTeamService $teams): JsonResponse
    {
        $authorize->authorize($request->user(), 'projects.view', $projectAssessment);

        return response()->json([
            'data' => collect($teams->seating($request->user(), $projectAssessment))->map(fn (array $row) => [
                'id' => $row['project']->id,
                'name' => $row['project']->name,
                'status' => $row['project']->status->value,
                'seats' => $row['seats'],
                'capacity' => $row['capacity'],
            ])->values()->all(),
        ]);
    }

    public function announce(Request $request, ProjectAssessment $projectAssessment, ProjectGradingService $grading): JsonResponse
    {
        $count = $grading->announce($request->user(), $projectAssessment);

        return response()->json(['data' => ['announced' => $count]]);
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
}
