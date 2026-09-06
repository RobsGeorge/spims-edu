<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\LiveQuiz;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Services\LiveQuiz\LiveQuizHostService;
use App\Services\LiveQuiz\LiveQuizPlayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachLiveQuizController extends Controller
{
    public function store(
        Request $request,
        CourseOffering $offering,
        LiveQuizHostService $host,
    ): JsonResponse {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'questions' => 'nullable|array',
            'questions.*.prompt' => 'required|string',
            'questions.*.time_limit_seconds' => 'nullable|integer|min:1|max:600',
            'questions.*.points' => 'nullable|integer|min:1|max:100000',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*.label' => 'required|string|max:500',
            'questions.*.options.*.is_correct' => 'nullable|boolean',
        ]);

        $quiz = $host->createQuiz(
            $request->user(),
            $offering,
            $data['title'],
            $data['questions'] ?? [],
        );

        return response()->json(['data' => $this->quizPayload($quiz)], 201);
    }

    public function start(Request $request, LiveQuiz $liveQuiz, LiveQuizHostService $host): JsonResponse
    {
        $session = $host->startSession($request->user(), $liveQuiz);

        return response()->json(['data' => $this->sessionPayload($session)], 201);
    }

    public function launch(
        Request $request,
        LiveQuizSession $liveQuizSession,
        LiveQuizHostService $host,
        LiveQuizPlayService $play,
    ): JsonResponse {
        $data = $request->validate([
            'question_id' => 'required|string',
        ]);

        $question = LiveQuizQuestion::query()->findOrFail($data['question_id']);
        $session = $host->launchQuestion($request->user(), $liveQuizSession, $question);

        return response()->json(['data' => $play->snapshot($session)]);
    }

    public function close(
        Request $request,
        LiveQuizSession $liveQuizSession,
        LiveQuizHostService $host,
        LiveQuizPlayService $play,
    ): JsonResponse {
        $session = $host->closeQuestion($request->user(), $liveQuizSession);

        return response()->json(['data' => $play->snapshot($session)]);
    }

    public function results(
        Request $request,
        LiveQuizSession $liveQuizSession,
        LiveQuizHostService $host,
        LiveQuizPlayService $play,
    ): JsonResponse {
        $session = $host->showResults($request->user(), $liveQuizSession);

        return response()->json(['data' => $play->snapshot($session)]);
    }

    public function end(
        Request $request,
        LiveQuizSession $liveQuizSession,
        LiveQuizHostService $host,
        LiveQuizPlayService $play,
    ): JsonResponse {
        $session = $host->endSession($request->user(), $liveQuizSession);

        return response()->json(['data' => $play->snapshot($session)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function quizPayload(LiveQuiz $quiz): array
    {
        $quiz->loadMissing('questions.options');

        return [
            'id' => $quiz->id,
            'offering_id' => $quiz->offering_id,
            'title' => $quiz->title,
            'status' => $quiz->status->value,
            'questions' => $quiz->questions->map(fn (LiveQuizQuestion $question) => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'position' => $question->position,
                'time_limit_seconds' => $question->time_limit_seconds,
                'points' => $question->points,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'is_correct' => $option->is_correct,
                    'position' => $option->position,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(LiveQuizSession $session): array
    {
        return [
            'id' => $session->id,
            'quiz_id' => $session->quiz_id,
            'join_code' => $session->join_code,
            'state' => $session->state->value,
            'current_question_id' => $session->current_question_id,
            'question_opened_at' => $session->question_opened_at?->toIso8601String(),
            'question_closes_at' => $session->question_closes_at?->toIso8601String(),
        ];
    }
}
