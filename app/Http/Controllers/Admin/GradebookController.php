<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ComponentKind;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Models\GradebookComponent;
use App\Services\Assessment\AssignmentService;
use App\Services\Gradebook\GradebookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GradebookController extends Controller
{
    public function show(CourseOffering $offering, GradebookService $gradebook): View
    {
        $grid = $gradebook->gridForOffering($offering);

        return view('admin.gradebook.show', [
            'offering' => $offering->load('course'),
            'components' => $grid['components'],
            'enrollments' => $grid['enrollments'],
            'weightSum' => $grid['weight_sum'],
            'componentKinds' => ComponentKind::cases(),
            'gradeUrls' => $this->gradeUrls($offering, $grid['components']),
        ]);
    }

    public function export(Request $request, CourseOffering $offering, GradebookService $gradebook): Response
    {
        $csv = $gradebook->exportCsv($request->user(), $offering);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="gradebook-'.$offering->id.'.csv"',
        ]);
    }

    public function addComponent(Request $request, CourseOffering $offering, GradebookService $gradebook): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'weight_percent' => 'required|numeric|min:0|max:100',
            'kind' => 'required|string',
        ]);

        $gradebook->addComponent($request->user(), $offering, $data);

        return back()->with('status', __('assessment.component_added'));
    }

    public function seedTemplate(Request $request, CourseOffering $offering, GradebookService $gradebook): RedirectResponse
    {
        $gradebook->seedFromTemplate($request->user(), $offering);

        return back()->with('status', __('assessment.template_seeded'));
    }

    public function submit(Request $request, CourseOffering $offering, GradebookService $gradebook): RedirectResponse
    {
        $gradebook->submitGrades($request->user(), $offering);

        return back()->with('status', __('assessment.grades_submitted'));
    }

    public function lock(Request $request, CourseOffering $offering, GradebookService $gradebook): RedirectResponse
    {
        $gradebook->lockGrades($request->user(), $offering);

        return back()->with('status', __('assessment.grades_locked'));
    }

    public function reopen(Request $request, CourseOffering $offering, GradebookService $gradebook): RedirectResponse
    {
        $gradebook->reopen($request->user(), $offering);

        return back()->with('status', __('assessment.grades_reopened'));
    }

    public function storeAssignment(Request $request, ContentItem $item, AssignmentService $assignments): RedirectResponse
    {
        $data = $request->validate([
            'instructions' => 'required|string',
            'due_date' => 'nullable|date',
            'max_points' => 'nullable|numeric|min:1',
            'component_id' => 'nullable|exists:gradebook_components,id',
            'late_penalty_override' => 'nullable|numeric|min:0|max:100',
            'delivery_mode' => 'nullable|in:ONLINE,OFFLINE',
            'resubmission_deadline' => 'nullable|date',
        ]);

        $assignments->create($request->user(), $item, $data);

        return back()->with('status', __('assessment.assignment_created'));
    }

    public function gradeSubmission(Request $request, AssignmentSubmission $submission, AssignmentService $assignments): RedirectResponse
    {
        $data = $request->validate([
            'raw_score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $assignments->grade($request->user(), $submission, (float) $data['raw_score'], $data['feedback'] ?? null);

        return back()->with('status', __('assessment.score_saved'));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, GradebookComponent>  $components
     * @return array<string, string|null>
     */
    private function gradeUrls(CourseOffering $offering, $components): array
    {
        $urls = [];
        foreach ($components as $component) {
            $urls[$component->id] = match ($component->kind) {
                ComponentKind::Assignment => route('teach.assignments.index', $offering),
                ComponentKind::Exam, ComponentKind::Quiz => $component->assessments->first()
                    ? route('admin.assessments.show', $component->assessments->first())
                    : route('admin.assessments.create', $offering),
                ComponentKind::Attendance => route('teach.attendance.index', $offering),
                ComponentKind::Discussion => route('discussions.board', $offering),
                ComponentKind::Project => route('teach.projects.index', $offering),
                default => null,
            };
        }

        return $urls;
    }
}
