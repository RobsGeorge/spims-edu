<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Teach\Concerns\GuardsTeachOffering;
use App\Models\CourseOffering;
use App\Models\DiscussionThread;
use App\Models\User;
use App\Services\Discussions\DiscussionService;
use App\Services\Teach\TeachAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscussionController extends Controller
{
    use GuardsTeachOffering;

    public function __construct(
        private readonly DiscussionService $discussions,
        private readonly TeachAccessService $teachAccess,
    ) {}

    public function index(Request $request, CourseOffering $offering): View
    {
        $this->guardTeach($request, $offering);

        $board = $this->discussions->ensureBoard($offering);
        $threads = $board
            ? DiscussionThread::query()
                ->where('board_id', $board->id)
                ->with(['author', 'grades.student'])
                ->orderByDesc('pinned')
                ->latest('created_at')
                ->get()
            : collect();

        return view('teach.discussions.index', [
            'offering' => $offering->load('course'),
            'board' => $board,
            'threads' => $threads,
            'students' => $this->discussions->enrolledStudents($offering),
            'canGrade' => $this->teachAccess->canGradeDiscussions($request->user(), $offering),
        ]);
    }

    public function grade(Request $request, CourseOffering $offering, DiscussionThread $thread): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertThreadOnOffering($offering, $thread);

        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $this->discussions->overrideGrade(
            $request->user(),
            $thread,
            User::query()->findOrFail($data['student_id']),
            (float) $data['score'],
            $data['feedback'] ?? null,
        );

        return back()->with('status', __('discussions.grade_saved'));
    }

    private function assertThreadOnOffering(CourseOffering $offering, DiscussionThread $thread): void
    {
        $thread->loadMissing('board');
        abort_unless($thread->board?->offering_id === $offering->id, 404);
    }
}
