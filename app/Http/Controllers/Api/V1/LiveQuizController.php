<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Services\LiveQuiz\LiveQuizPlayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveQuizController extends Controller
{
    public function join(Request $request, LiveQuizPlayService $play): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:16',
        ]);

        $participant = $play->join($request->user(), $data['code']);
        $session = $participant->session()->with(['quiz', 'currentQuestion.options'])->firstOrFail();
        $created = $participant->wasRecentlyCreated;

        return response()->json([
            'data' => $play->snapshot($session, $participant),
        ], $created ? 201 : 200);
    }

    public function show(Request $request, LiveQuizSession $liveQuizSession, LiveQuizPlayService $play): JsonResponse
    {
        return response()->json([
            'data' => $play->poll($request->user(), $liveQuizSession),
        ]);
    }

    public function answer(
        Request $request,
        LiveQuizSession $liveQuizSession,
        LiveQuizQuestion $liveQuizQuestion,
        LiveQuizPlayService $play,
    ): JsonResponse {
        $data = $request->validate([
            'option_id' => 'required|string',
        ]);

        $answer = $play->answer(
            $request->user(),
            $liveQuizSession,
            $liveQuizQuestion,
            $data['option_id'],
        );

        return response()->json([
            'data' => [
                'id' => $answer->id,
                'question_id' => $answer->question_id,
                'option_id' => $answer->option_id,
                'score' => $answer->score,
                'answered_at' => $answer->answered_at?->toIso8601String(),
            ],
        ], 201);
    }
}
