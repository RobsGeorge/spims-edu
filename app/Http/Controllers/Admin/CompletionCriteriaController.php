<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompletionCriterionKind;
use App\Http\Controllers\Controller;
use App\Models\CompletionCriterion;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Services\Completion\CompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompletionCriteriaController extends Controller
{
    public function index(Request $request, Course $course, CompletionService $completion): View
    {
        return view('admin.completion-criteria.index', [
            'course' => $course,
            'criteria' => $completion->criteriaForCourse($request->user(), $course),
            'offerings' => CourseOffering::query()->where('course_id', $course->id)->get(),
            'kinds' => CompletionCriterionKind::cases(),
        ]);
    }

    public function store(Request $request, Course $course, CompletionService $completion): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', array_column(CompletionCriterionKind::cases(), 'value'))],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'content_item_id' => ['nullable', 'exists:content_items,id'],
            'is_required' => ['nullable', 'boolean'],
            'offering_id' => ['nullable', 'exists:course_offerings,id'],
        ]);
        $data['is_required'] = $request->boolean('is_required');

        $completion->addCriterion($request->user(), $course, $data);

        return back()->with('status', __('completion.criterion_added'));
    }

    public function destroy(Request $request, CompletionCriterion $criterion, CompletionService $completion): RedirectResponse
    {
        $criterion->loadMissing('offering');
        $courseId = $criterion->course_id ?? $criterion->offering?->course_id;
        $completion->deleteCriterion($request->user(), $criterion);

        return redirect()
            ->route('admin.completion-criteria.index', ['course' => $courseId])
            ->with('status', __('completion.criterion_removed'));
    }
}
