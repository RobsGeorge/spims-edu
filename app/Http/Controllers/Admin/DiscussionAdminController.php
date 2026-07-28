<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\DiscussionThread;
use App\Models\User;
use App\Services\Discussions\DiscussionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiscussionAdminController extends Controller
{
    public function configure(Request $request, CourseOffering $offering, DiscussionService $discussions): RedirectResponse
    {
        $data = $request->validate(['allow_student_threads' => 'required|boolean']);
        $discussions->configureBoard($request->user(), $offering, $request->boolean('allow_student_threads'));

        return back()->with('status', __('live.board_configured'));
    }

    public function moderate(Request $request, DiscussionThread $thread, DiscussionService $discussions): RedirectResponse
    {
        $data = $request->validate([
            'locked' => 'nullable|boolean',
            'pinned' => 'nullable|boolean',
        ]);

        $discussions->moderate($request->user(), $thread, [
            'locked' => $request->boolean('locked'),
            'pinned' => $request->boolean('pinned'),
        ]);

        return back()->with('status', __('live.moderated'));
    }

    public function grade(Request $request, DiscussionThread $thread, DiscussionService $discussions): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'final_score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $discussions->overrideGrade(
            $request->user(),
            $thread,
            User::query()->findOrFail($data['student_id']),
            (float) $data['final_score'],
            $data['feedback'] ?? null
        );

        return back()->with('status', __('live.grade_saved'));
    }
}
