<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Services\Assessment\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function show(Assignment $assignment): View
    {
        return view('assignments.show', [
            'assignment' => $assignment->load('contentItem'),
            'submission' => $assignment->submissions()->where('student_id', auth()->id())->first(),
        ]);
    }

    public function submit(Request $request, Assignment $assignment, AssignmentService $assignments): RedirectResponse
    {
        $data = $request->validate([
            'text_body' => 'nullable|string',
            'file_url' => 'nullable|string|max:500',
        ]);

        $assignments->submit(
            $request->user(),
            $assignment,
            $data['text_body'] ?? null,
            $data['file_url'] ?? null
        );

        return back()->with('status', __('assessment.assignment_submitted'));
    }
}
