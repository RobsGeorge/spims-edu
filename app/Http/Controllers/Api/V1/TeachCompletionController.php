<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\ModuleStudentAssessment;
use App\Models\StudentNote;
use App\Models\User;
use App\Models\Week;
use App\Services\Completion\ModuleAssessmentService;
use App\Services\Completion\StudentNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachCompletionController extends Controller
{
    public function notes(Request $request, CourseOffering $offering, User $student, StudentNoteService $notes): JsonResponse
    {
        $items = $notes->forStudent($request->user(), $offering, $student);

        return response()->json([
            'data' => $items->map(fn (StudentNote $note) => $this->notePayload($note))->values(),
        ]);
    }

    public function storeNote(Request $request, CourseOffering $offering, User $student, StudentNoteService $notes): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $note = $notes->add($request->user(), $offering, $student, $data['body']);

        return response()->json(['data' => $this->notePayload($note)], 201);
    }

    public function rate(
        Request $request,
        CourseOffering $offering,
        Week $week,
        User $student,
        ModuleAssessmentService $modules,
    ): JsonResponse {
        abort_unless($week->offering_id === $offering->id, 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:'.ModuleStudentAssessment::RATING_MIN, 'max:'.ModuleStudentAssessment::RATING_MAX],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $assessment = $modules->rate(
            $request->user(),
            $week,
            $student,
            (int) $data['rating'],
            $data['comment'] ?? null,
        );

        return response()->json(['data' => $this->assessmentPayload($assessment)]);
    }

    /** @return array<string, mixed> */
    private function notePayload(StudentNote $note): array
    {
        return [
            'id' => $note->id,
            'offering_id' => $note->offering_id,
            'student_id' => $note->student_id,
            'author_id' => $note->author_id,
            'body' => $note->body,
            'visibility' => $note->visibility,
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function assessmentPayload(ModuleStudentAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'week_id' => $assessment->week_id,
            'student_id' => $assessment->student_id,
            'rating' => $assessment->rating,
            'comment' => $assessment->comment,
            'assessed_by_id' => $assessment->assessed_by_id,
        ];
    }
}
