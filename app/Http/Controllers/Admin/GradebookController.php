<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Services\Assessment\AssignmentService;
use App\Services\Gradebook\GradebookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradebookController extends Controller
{
    public function show(CourseOffering $offering, GradebookService $gradebook): View
    {
        $enrollments = \App\Models\Enrollment::query()
            ->where('offering_id', $offering->id)
            ->with('student')
            ->get()
            ->map(function ($e) use ($gradebook) {
                $computed = $gradebook->computeEnrollment($e);
                $e->computed = $computed;

                return $e;
            });

        return view('admin.gradebook.show', [
            'offering' => $offering->load('course'),
            'components' => \App\Models\GradebookComponent::query()->where('offering_id', $offering->id)->get(),
            'enrollments' => $enrollments,
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
}
