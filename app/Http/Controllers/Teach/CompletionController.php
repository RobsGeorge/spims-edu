<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Teach\Concerns\IssuesOfferingCloseConfirmation;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use App\Services\Completion\CompletionService;
use App\Services\Completion\ModuleAssessmentService;
use App\Services\Completion\OfferingClosingService;
use App\Services\Completion\StudentNoteService;
use App\Services\Teach\TeachAccessService;
use App\Support\AuthorizeService;
use App\Support\ConfirmationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompletionController extends Controller
{
    use IssuesOfferingCloseConfirmation;

    public function __construct(
        private readonly TeachAccessService $teachAccess,
        private readonly AuthorizeService $authorize,
        private readonly ConfirmationToken $confirm,
        private readonly OfferingClosingService $closing,
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
            'closeToken' => $this->offeringCloseToken(
                $user,
                $offering,
                $this->authorize,
                $this->confirm,
                $this->closing,
            ),
        ]);
    }

    public function close(Request $request, CourseOffering $offering): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->teachAccess->canTeach($user), 403);
        $this->teachAccess->assertCanTeachOffering($user, $offering);

        $this->authorize->authorize($user, 'offering.close', $offering);

        $this->confirm->consume(
            'offering.close.'.$offering->id,
            $request->input('confirmation_token'),
        );

        try {
            $this->closing->close($user, $offering);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('teach.completion.show', $offering)
            ->with('status', __('completion.closed'));
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
