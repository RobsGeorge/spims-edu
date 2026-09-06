<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\DiscussionGrade;
use App\Models\DiscussionThread;
use App\Models\User;
use App\Services\Discussions\DiscussionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachDiscussionController extends Controller
{
    public function threads(Request $request, CourseOffering $offering, DiscussionService $discussions): JsonResponse
    {
        $threads = $discussions->threadsForOffering($request->user(), $offering);

        return response()->json([
            'data' => $threads->map(fn (DiscussionThread $thread) => [
                'id' => $thread->id,
                'title' => $thread->title,
                'locked' => $thread->locked,
                'pinned' => $thread->pinned,
                'is_graded' => $thread->is_graded,
                'author_id' => $thread->author_id,
                'created_at' => $thread->created_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function moderate(
        Request $request,
        DiscussionThread $discussionThread,
        DiscussionService $discussions,
    ): JsonResponse {
        $flags = $request->validate([
            'locked' => 'nullable|boolean',
            'pinned' => 'nullable|boolean',
        ]);

        $thread = $discussions->moderate($request->user(), $discussionThread, array_filter(
            $flags,
            fn ($value) => $value !== null,
        ));

        return response()->json([
            'data' => [
                'id' => $thread->id,
                'title' => $thread->title,
                'locked' => $thread->locked,
                'pinned' => $thread->pinned,
            ],
        ]);
    }

    public function grade(
        Request $request,
        DiscussionThread $discussionThread,
        DiscussionService $discussions,
    ): JsonResponse {
        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $grade = $discussions->overrideGrade(
            $request->user(),
            $discussionThread,
            User::query()->findOrFail($data['student_id']),
            (float) $data['score'],
            $data['feedback'] ?? null,
        );

        return response()->json(['data' => $this->gradePayload($grade)]);
    }

    /** @return array<string, mixed> */
    private function gradePayload(DiscussionGrade $grade): array
    {
        return [
            'id' => $grade->id,
            'thread_id' => $grade->thread_id,
            'student_id' => $grade->student_id,
            'final_score' => $grade->final_score,
            'overridden' => $grade->overridden,
            'feedback' => $grade->feedback,
        ];
    }
}
