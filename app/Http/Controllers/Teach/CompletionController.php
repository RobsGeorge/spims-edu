<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use App\Services\Completion\CompletionService;
use App\Services\Completion\ModuleAssessmentService;
use App\Services\Completion\StudentNoteService;
use App\Services\Teach\TeachAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompletionController extends Controller
{
    public function __construct(
        private readonly TeachAccessService $teachAccess,
    ) {}

    public function show(
        Request $request,
        CourseOffering $offering,
        CompletionService $completion,
        StudentNoteService $notes,
        ModuleAssessmentService $modules,
    ): View {
        $user = $request->user();
        abort_unless($this->teachAccess->canTeach($user), 403);
        $this->teachAccess->assertCanTeachOffering($user, $offering);

        $offering->load(['course', 'weeks']);
        $studentId = $request->query('student_id');
        $student = $studentId ? User::query()->find($studentId) : null;

        return view('teach.completion.show', [
            'offering' => $offering,
            'results' => $completion->cohort($user, $offering),
            'notes' => $student ? $notes->forStudent($user, $offering, $student) : collect(),
            'selectedStudent' => $student,
            'weeks' => $offering->weeks,
            'weekAssessments' => $offering->weeks->mapWithKeys(
                fn (Week $week) => [$week->id => $modules->forWeek($user, $week)->keyBy('student_id')]
            ),
        ]);
    }

    public function storeNote(Request $request, CourseOffering $offering, User $student, StudentNoteService $notes): RedirectResponse
    {
        $this->teachAccess->assertCanTeachOffering($request->user(), $offering);

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $notes->add($request->user(), $offering, $student, $data['body']);

        return redirect()
            ->route('teach.completion.show', ['offering' => $offering, 'student_id' => $student->id])
            ->with('status', __('completion.note_saved'));
    }

    public function rate(Request $request, CourseOffering $offering, Week $week, User $student, ModuleAssessmentService $modules): RedirectResponse
    {
        $this->teachAccess->assertCanTeachOffering($request->user(), $offering);
        abort_unless($week->offering_id === $offering->id, 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $modules->rate($request->user(), $week, $student, (int) $data['rating'], $data['comment'] ?? null);

        return back()->with('status', __('completion.module_assessment_saved'));
    }
}
