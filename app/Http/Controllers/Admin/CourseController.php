<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentTemplate;
use App\Models\Course;
use App\Services\Academics\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(CourseService $courses): View
    {
        return view('admin.courses.index', [
            'courses' => Course::query()->withCount('interestFlags')->orderBy('code')->paginate(20),
            'interestCounts' => $courses->interestCounts(),
        ]);
    }

    public function create(): View
    {
        return view('admin.courses.create', [
            'templates' => AssessmentTemplate::query()->orderBy('name')->get(),
            'prerequisiteOptions' => Course::query()->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, CourseService $service): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:32|unique:courses,code',
            'title' => 'required|string|max:255',
            'credit_hours' => 'required|integer|min:0',
            'default_price_usd' => 'nullable|integer|min:0',
            'default_price_egp' => 'nullable|integer|min:0',
            'is_free' => 'boolean',
            'is_standalone' => 'boolean',
            'passing_threshold' => 'nullable|numeric|min:0|max:100',
            'assessment_template_id' => 'nullable|exists:assessment_templates,id',
            'prerequisite_id' => 'nullable|exists:courses,id',
        ]);

        $course = $service->create($request->user(), $data);

        if (! empty($data['prerequisite_id'])) {
            $service->addPrerequisite($request->user(), $course, $data['prerequisite_id']);
        }

        return redirect()->route('admin.courses.index')->with('status', __('academics.course_created'));
    }

    public function show(Course $course): View
    {
        $course->load(['prerequisites', 'assessmentTemplate', 'interestFlags']);

        return view('admin.courses.show', [
            'course' => $course,
            'prerequisiteOptions' => Course::query()
                ->where('active', true)
                ->where('id', '!=', $course->id)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function addPrerequisite(Request $request, Course $course, CourseService $service): RedirectResponse
    {
        $data = $request->validate([
            'prerequisite_id' => 'required|exists:courses,id',
        ]);

        $service->addPrerequisite($request->user(), $course, $data['prerequisite_id']);

        return back()->with('status', __('academics.prerequisite_added'));
    }
}
