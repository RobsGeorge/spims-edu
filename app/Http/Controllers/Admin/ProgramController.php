<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Services\Academics\ProgramService;
use App\Services\Reports\AcademicStandingService;
use App\Support\AuthorizeService;
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

    public function show(
        Request $request,
        Program $program,
        AcademicStandingService $standing,
        AuthorizeService $authorize,
    ): View {
        $program->load(['programCourses.course', 'gradingScheme']);

        return view('admin.programs.show', array_merge([
            'program' => $program,
            'courses' => Course::query()->where('active', true)->orderBy('code')->get(),
            'requirements' => RequirementType::cases(),
            'canManageProgram' => $authorize->allows($request->user(), 'programs.manage'),
        ], $this->standingViewData($request, $program, $standing, $authorize)));
    }

    public function edit(
        Request $request,
        Program $program,
        AcademicStandingService $standing,
        AuthorizeService $authorize,
    ): View {
        return view('admin.programs.edit', array_merge([
            'program' => $program,
            'types' => ProgramType::cases(),
            'schemes' => GradingScheme::query()->orderBy('name')->get(),
        ], $this->standingViewData($request, $program, $standing, $authorize)));
    }

    public function update(Request $request, Program $program, ProgramService $service): RedirectResponse
    {
        $data = $request->validate($this->programRules());
        $data['active'] = $request->boolean('active');

        $service->update($request->user(), $program, $data);

        return redirect()->route('admin.programs.show', $program)->with('status', __('academics.program_updated'));
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

    public function detachCourse(Request $request, Program $program, ProgramCourse $programCourse, ProgramService $service): RedirectResponse
    {
        $service->detachCourse($request->user(), $program, $programCourse);

        return back()->with('status', __('academics.course_detached'));
    }

    public function updateStanding(Request $request, Program $program, AcademicStandingService $standing): RedirectResponse
    {
        $data = $request->validate([
            'good_min' => ['nullable', 'integer', 'min:0', 'max:400'],
            'suspension_below' => ['nullable', 'integer', 'min:0', 'max:400'],
        ]);

        $goodMin = array_key_exists('good_min', $data) && $data['good_min'] !== null
            ? (int) $data['good_min']
            : null;
        $suspensionBelow = array_key_exists('suspension_below', $data) && $data['suspension_below'] !== null
            ? (int) $data['suspension_below']
            : null;

        $standing->updateProgramOverrides($request->user(), $program, $goodMin, $suspensionBelow);

        $cleared = $goodMin === null && $suspensionBelow === null;

        return redirect()
            ->route('admin.programs.show', $program)
            ->with('status', $cleared
                ? __('reports.program_thresholds_cleared')
                : __('reports.program_thresholds_saved'))
            ->withFragment('standing');
    }

    /**
     * @return array<string, mixed>
     */
    private function programRules(): array
    {
        return [
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
            'active' => 'sometimes|boolean',
        ];
    }

    /**
     * @return array{schoolThresholds: array{good_min: int, suspension_below: int}, standingThresholds: array{good_min: int, suspension_below: int, source: string}, canManageStanding: bool}
     */
    private function standingViewData(
        Request $request,
        Program $program,
        AcademicStandingService $standing,
        AuthorizeService $authorize,
    ): array {
        return [
            'schoolThresholds' => $standing->thresholds(),
            'standingThresholds' => $standing->thresholdsFor($program),
            'canManageStanding' => $authorize->allows($request->user(), 'academic_standing.manage'),
        ];
    }
}
