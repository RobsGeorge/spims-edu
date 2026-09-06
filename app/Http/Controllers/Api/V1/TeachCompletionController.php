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
use App\Support\Api\ConditionalGet;
use App\Support\Api\IdempotencyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachCompletionController extends Controller
{
    public function notes(Request $request, CourseOffering $offering, User $student, StudentNoteService $notes, ConditionalGet $conditional): JsonResponse
    {
        $items = $notes->forStudent($request->user(), $offering, $student);

        return $conditional->json($request, [
            'data' => $items->map(fn (StudentNote $note) => $this->notePayload($note))->values(),
        ]);
    }

    public function storeNote(Request $request, CourseOffering $offering, User $student, StudentNoteService $notes, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.notes.store:'.$offering->id.':'.$student->id,
            $request->header('Idempotency-Key'),
            fn () => $this->notePayload($notes->add($request->user(), $offering, $student, $data['body'])),
        );

        return response()->json(['data' => $payload], 201);
    }

    public function rate(
        Request $request,
        CourseOffering $offering,
        Week $week,
        User $student,
        ModuleAssessmentService $modules,
        IdempotencyStore $idempotency,
    ): JsonResponse {
        abort_unless($week->offering_id === $offering->id, 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:'.ModuleStudentAssessment::RATING_MIN, 'max:'.ModuleStudentAssessment::RATING_MAX],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.modules.rate:'.$week->id.':'.$student->id,
            $request->header('Idempotency-Key'),
            fn () => $this->assessmentPayload($modules->rate(
                $request->user(),
                $week,
                $student,
                (int) $data['rating'],
                $data['comment'] ?? null,
            )),
        );

        return response()->json(['data' => $payload]);
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
