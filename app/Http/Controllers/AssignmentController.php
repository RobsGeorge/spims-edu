<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Services\Assessment\AssignmentService;
use App\Services\Learning\OfferingAccessService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function show(Assignment $assignment, OfferingAccessService $access, ObjectStorageService $storage): View
    {
        $access->assertCanAccessAssignment(auth()->user(), $assignment);

        $submission = $assignment->submissions()->where('student_id', auth()->id())->first();

        return view('assignments.show', [
            'assignment' => $assignment->load('contentItem.week.offering.course'),
            'submission' => $submission,
            'submissionFileUrl' => $submission?->file_url
                ? $storage->temporaryUrl($submission->file_url)
                : null,
        ]);
    }

    public function submit(Request $request, Assignment $assignment, AssignmentService $assignments, OfferingAccessService $access): RedirectResponse
    {
        $access->assertCanAccessAssignment($request->user(), $assignment);

        if ($request->exists('file_url')) {
            throw ValidationException::withMessages([
                'file_url' => [__('assessment.file_url_not_allowed')],
            ]);
        }

        $data = $request->validate([
            'text_body' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
        ]);

        $path = null;
        if ($request->hasFile('file')) {
            $path = $assignments->storeSubmissionFile(
                $request->user(),
                $assignment,
                $request->file('file')
            );
        }

        $assignments->submit(
            $request->user(),
            $assignment,
            $data['text_body'] ?? null,
            $path
        );

        return back()->with('status', __('assessment.assignment_submitted'));
    }
}
