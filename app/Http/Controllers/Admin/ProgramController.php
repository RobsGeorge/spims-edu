<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Services\Academics\ProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function index(): View
    {
        return view('admin.programs.index', [
            'programs' => Program::query()->with('gradingScheme')->orderBy('code')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.programs.create', [
            'types' => ProgramType::cases(),
            'schemes' => GradingScheme::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ProgramService $service): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:32|unique:programs,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', array_column(ProgramType::cases(), 'value')),
            'passing_threshold' => 'nullable|numeric|min:0|max:100',
            'max_credits_per_semester' => 'required|integer|min:1',
            'max_courses_per_semester' => 'required|integer|min:1',
            'max_semesters_to_graduate' => 'required|integer|min:1',
            'elective_credits_required' => 'nullable|integer|min:0',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
            'grading_scheme_id' => 'nullable|exists:grading_schemes,id',
        ]);

        $program = $service->create($request->user(), $data);

        return redirect()->route('admin.programs.show', $program)->with('status', __('academics.program_created'));
    }

    public function show(Program $program): View
    {
        $program->load(['programCourses.course', 'gradingScheme']);

        return view('admin.programs.show', [
            'program' => $program,
            'courses' => Course::query()->where('active', true)->orderBy('code')->get(),
            'requirements' => RequirementType::cases(),
        ]);
    }

    public function attachCourse(Request $request, Program $program, ProgramService $service): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'requirement' => 'required|in:'.implode(',', array_column(RequirementType::cases(), 'value')),
            'year_level' => 'nullable|integer|min:1|max:10',
        ]);

        $service->attachCourse(
            $request->user(),
            $program,
            $data['course_id'],
            $data['requirement'],
            $data['year_level'] ?? null
        );

        return back()->with('status', __('academics.course_attached'));
    }
}
