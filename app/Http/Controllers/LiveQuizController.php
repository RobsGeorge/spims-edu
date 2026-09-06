<?php

namespace App\Http\Controllers;

use App\Enums\LiveQuizSessionState;
use App\Exceptions\ConflictException;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Services\LiveQuiz\LiveQuizPlayService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LiveQuizController extends Controller
{
    public function create(): View
    {
        return view('live-quiz.join');
    }

    public function join(Request $request, LiveQuizPlayService $play): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:16',
        ]);

        try {
            $participant = $play->join($request->user(), $data['code']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([
                'code' => [__('live_quiz.invalid_code')],
            ]);
        } catch (ConflictException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('live-quiz.sessions.show', $participant->session_id)
            ->with('status', __('live_quiz.joined'));
    }

    public function show(Request $request, LiveQuizSession $session, LiveQuizPlayService $play): View
    {
        $snapshot = $play->poll($request->user(), $session);

        return view('live-quiz.play', [
            'snapshot' => $snapshot,
            'autoRefresh' => $snapshot['state'] !== LiveQuizSessionState::Ended->value,
        ]);
    }

    public function answer(
        Request $request,
        LiveQuizSession $session,
        LiveQuizQuestion $question,
        LiveQuizPlayService $play,
    ): RedirectResponse {
        $data = $request->validate([
            'option_id' => 'required|string',
        ]);

        try {
            $play->answer($request->user(), $session, $question, $data['option_id']);
        } catch (ConflictException $e) {
            return redirect()
                ->route('live-quiz.sessions.show', $session)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('live-quiz.sessions.show', $session)
            ->with('status', __('live_quiz.answered'));
    }
}
