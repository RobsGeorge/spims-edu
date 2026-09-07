<?php

namespace App\Http\Controllers;

use App\Enums\AdvisingHoldKind;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\AdvisingHold;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Completion\StudentNoteService;
use App\Services\Enrollment\AdvisingService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdvisingController extends Controller
{
    public function __construct(
        private readonly AdvisingService $advising,
        private readonly AuthorizeService $authorize,
    ) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->advising->assertCanOpenRoster($actor);

        return view('advising.index', [
            'assignments' => $this->advising->rosterFor($actor),
            'canAssign' => $this->authorize->allows($actor, 'advising.assign'),
            'students' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('role', RoleType::Student))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'advisors' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('role', RoleType::Instructor))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'programs' => Program::query()->where('active', true)->orderBy('code')->get(),
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'advisor_id' => 'required|exists:users,id',
            'program_id' => 'nullable|exists:programs,id',
        ]);

        $student = User::query()->findOrFail($data['student_id']);
        $advisor = User::query()->findOrFail($data['advisor_id']);

        $this->advising->assignAdvisor(
            $request->user(),
            $student,
            $advisor,
            $data['program_id'] ?? null
        );

        return back()->with('status', __('advising.assigned'));
    }

    public function show(Request $request, User $student, StudentNoteService $notes): View
    {
        $actor = $request->user();
        $this->advising->assertCanManageAdvisee($actor, $student);

        $offeringNotes = collect();
        $offering = null;
        if ($request->filled('offering_id')) {
            $offering = CourseOffering::query()->with('course')->find($request->string('offering_id'));
            if ($offering !== null) {
                try {
                    $offeringNotes = $notes->forStudent($actor, $offering, $student);
                } catch (AuthorizationException) {
                    $offeringNotes = collect();
                }
            }
        }

        return view('advising.show', [
            'student' => $student->load('roles'),
            'holds' => $this->advising->holdsFor($student),
            'canHold' => $this->advising->allowsHold($actor, $student),
            'programs' => StudentProgram::query()
                ->where('student_id', $student->id)
                ->with('program')
                ->get(),
            'holdKinds' => AdvisingHoldKind::cases(),
            'notes' => $offeringNotes,
            'offering' => $offering,
        ]);
    }

    public function placeHold(Request $request, User $student): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(AdvisingHoldKind::class)],
            'reason' => 'required|string|max:2000',
        ]);

        $this->advising->placeHold(
            $request->user(),
            $student,
            AdvisingHoldKind::from($data['kind']),
            $data['reason']
        );

        return back()->with('status', __('advising.hold_placed'));
    }

    public function releaseHold(Request $request, AdvisingHold $hold): RedirectResponse
    {
        $this->advising->releaseHold($request->user(), $hold);

        return back()->with('status', __('advising.hold_released'));
    }
}
