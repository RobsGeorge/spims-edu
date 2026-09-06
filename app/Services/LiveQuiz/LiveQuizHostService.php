<?php

namespace App\Services\LiveQuiz;

use App\Enums\LiveQuizSessionState;
use App\Enums\LiveQuizStatus;
use App\Exceptions\ConflictException;
use App\Models\CourseOffering;
use App\Models\LiveQuiz;
use App\Models\LiveQuizOption;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LiveQuizHostService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @param  array<int, array{prompt: string, time_limit_seconds?: int, points?: int, options: array<int, array{label: string, is_correct?: bool}>}>  $questions
     */
    public function createQuiz(User $actor, CourseOffering $offering, string $title, array $questions = []): LiveQuiz
    {
        $this->authorize->authorize($actor, 'live_quiz.manage', $offering);

        return $this->audit->withAudit($actor, 'live_quiz.create', function () use ($actor, $offering, $title, $questions) {
            $quiz = LiveQuiz::query()->create([
                'offering_id' => $offering->id,
                'title' => $title,
                'status' => $questions === [] ? LiveQuizStatus::Draft : LiveQuizStatus::Ready,
                'created_by' => $actor->id,
            ]);

            foreach (array_values($questions) as $index => $payload) {
                $this->insertQuestion($quiz, $payload, $index + 1);
            }

            if ($questions !== [] && $quiz->questions()->count() === 0) {
                $quiz->update(['status' => LiveQuizStatus::Draft]);
            }

            return $quiz->fresh(['questions.options']) ?? $quiz;
        }, 'LiveQuiz');
    }

    /**
     * @param  array{prompt: string, time_limit_seconds?: int, points?: int, options: array<int, array{label: string, is_correct?: bool}>}  $payload
     */
    public function addQuestion(User $actor, LiveQuiz $quiz, array $payload): LiveQuizQuestion
    {
        $this->authorize->authorize($actor, 'live_quiz.manage', $quiz);

        return $this->audit->withAudit($actor, 'live_quiz.question_add', function () use ($quiz, $payload) {
            $position = ((int) $quiz->questions()->max('position')) + 1;
            $question = $this->insertQuestion($quiz, $payload, $position);

            if ($quiz->status === LiveQuizStatus::Draft && $quiz->questions()->exists()) {
                $quiz->update(['status' => LiveQuizStatus::Ready]);
            }

            return $question;
        }, 'LiveQuizQuestion');
    }

    public function markReady(User $actor, LiveQuiz $quiz): LiveQuiz
    {
        $this->authorize->authorize($actor, 'live_quiz.manage', $quiz);

        if (! $quiz->questions()->exists()) {
            throw new ConflictException(__('live_quiz.no_questions'));
        }

        return $this->audit->withAudit($actor, 'live_quiz.ready', function () use ($quiz) {
            $quiz->update(['status' => LiveQuizStatus::Ready]);

            return $quiz->fresh() ?? $quiz;
        }, 'LiveQuiz');
    }

    public function startSession(User $actor, LiveQuiz $quiz): LiveQuizSession
    {
        $this->authorize->authorize($actor, 'live_quiz.host', $quiz);

        if ($quiz->status !== LiveQuizStatus::Ready) {
            throw new ConflictException(__('live_quiz.quiz_not_ready'));
        }

        if (! $quiz->questions()->exists()) {
            throw new ConflictException(__('live_quiz.no_questions'));
        }

        return $this->audit->withAudit($actor, 'live_quiz.session_start', function () use ($actor, $quiz) {
            if ($this->activeSessionFor($quiz) !== null) {
                throw new ConflictException(__('live_quiz.session_already_active'));
            }

            return LiveQuizSession::query()->create([
                'quiz_id' => $quiz->id,
                'join_code' => $this->uniqueJoinCode(),
                'state' => LiveQuizSessionState::Lobby,
                'current_question_id' => null,
                'question_opened_at' => null,
                'question_closes_at' => null,
                'host_id' => $actor->id,
            ]);
        }, 'LiveQuizSession');
    }

    public function launchQuestion(User $actor, LiveQuizSession $session, LiveQuizQuestion $question): LiveQuizSession
    {
        $this->authorize->authorize($actor, 'live_quiz.host', $session);

        return $this->audit->withAudit($actor, 'live_quiz.question_launch', function () use ($session, $question) {
            $locked = $this->lockSession($session);
            $this->assertQuestionBelongs($locked, $question);
            $this->assertLaunchable($locked);

            if ($locked->answers()->where('question_id', $question->id)->exists()) {
                throw new ConflictException(__('live_quiz.question_already_played'));
            }

            $openedAt = now();
            $locked->update([
                'state' => LiveQuizSessionState::QuestionOpen,
                'current_question_id' => $question->id,
                'question_opened_at' => $openedAt,
                'question_closes_at' => $openedAt->copy()->addSeconds($question->time_limit_seconds),
            ]);

            return $locked->fresh(['currentQuestion.options', 'quiz']) ?? $locked;
        }, 'LiveQuizSession');
    }

    public function closeQuestion(User $actor, LiveQuizSession $session): LiveQuizSession
    {
        $this->authorize->authorize($actor, 'live_quiz.host', $session);

        return $this->audit->withAudit($actor, 'live_quiz.question_close', function () use ($session) {
            $locked = $this->lockSession($session);
            $this->assertState($locked, [LiveQuizSessionState::QuestionOpen], 'live_quiz.invalid_transition');
            $locked->update(['state' => LiveQuizSessionState::QuestionClosed]);

            return $locked->fresh(['currentQuestion.options', 'quiz']) ?? $locked;
        }, 'LiveQuizSession');
    }

    public function showResults(User $actor, LiveQuizSession $session): LiveQuizSession
    {
        $this->authorize->authorize($actor, 'live_quiz.host', $session);

        return $this->audit->withAudit($actor, 'live_quiz.results_show', function () use ($session) {
            $locked = $this->lockSession($session);
            $this->assertState($locked, [LiveQuizSessionState::QuestionClosed], 'live_quiz.invalid_transition');
            $locked->update(['state' => LiveQuizSessionState::Results]);

            return $locked->fresh(['currentQuestion.options', 'quiz']) ?? $locked;
        }, 'LiveQuizSession');
    }

    public function nextQuestion(User $actor, LiveQuizSession $session): LiveQuizSession
    {
        $this->authorize->authorize($actor, 'live_quiz.host', $session);

        $session->loadMissing('currentQuestion');
        $currentPosition = (int) ($session->currentQuestion?->position ?? 0);
        $next = LiveQuizQuestion::query()
            ->where('quiz_id', $session->quiz_id)
            ->where('position', '>', $currentPosition)
            ->orderBy('position')
            ->first();

        if ($next === null) {
            throw new ConflictException(__('live_quiz.no_more_questions'));
        }

        return $this->launchQuestion($actor, $session, $next);
    }

    public function endSession(User $actor, LiveQuizSession $session): LiveQuizSession
    {
        $this->authorize->authorize($actor, 'live_quiz.host', $session);

        return $this->audit->withAudit($actor, 'live_quiz.session_end', function () use ($session) {
            $locked = $this->lockSession($session);

            if ($locked->state === LiveQuizSessionState::Ended) {
                throw new ConflictException(__('live_quiz.invalid_transition'));
            }

            $locked->update(['state' => LiveQuizSessionState::Ended]);

            return $locked->fresh(['currentQuestion.options', 'quiz']) ?? $locked;
        }, 'LiveQuizSession');
    }

    /**
     * @param  array{prompt: string, time_limit_seconds?: int, points?: int, options: array<int, array{label: string, is_correct?: bool}>}  $payload
     */
    private function insertQuestion(LiveQuiz $quiz, array $payload, int $position): LiveQuizQuestion
    {
        $options = $payload['options'] ?? [];
        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'options' => [__('live_quiz.need_options')],
            ]);
        }

        $hasCorrect = collect($options)->contains(fn (array $option): bool => (bool) ($option['is_correct'] ?? false));
        if (! $hasCorrect) {
            throw ValidationException::withMessages([
                'options' => [__('live_quiz.need_correct_option')],
            ]);
        }

        $question = LiveQuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'prompt' => $payload['prompt'],
            'position' => $position,
            'time_limit_seconds' => (int) ($payload['time_limit_seconds'] ?? 30),
            'points' => (int) ($payload['points'] ?? 1000),
        ]);

        foreach (array_values($options) as $index => $option) {
            LiveQuizOption::query()->create([
                'question_id' => $question->id,
                'label' => $option['label'],
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'position' => $index + 1,
            ]);
        }

        return $question->fresh('options') ?? $question;
    }

    private function uniqueJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        for ($attempt = 0; $attempt < 16; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            if (! LiveQuizSession::query()->where('join_code', $code)->exists()) {
                return $code;
            }
        }

        return strtoupper(Str::ulid()->toString())[0].substr((string) Str::ulid(), -5);
    }

    private function activeSessionFor(LiveQuiz $quiz): ?LiveQuizSession
    {
        return LiveQuizSession::query()
            ->where('quiz_id', $quiz->id)
            ->where('state', '!=', LiveQuizSessionState::Ended->value)
            ->first();
    }

    private function lockSession(LiveQuizSession $session): LiveQuizSession
    {
        /** @var LiveQuizSession $locked */
        $locked = LiveQuizSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
        $locked->load(['quiz', 'currentQuestion']);

        return $locked;
    }

    private function assertQuestionBelongs(LiveQuizSession $session, LiveQuizQuestion $question): void
    {
        if ($question->quiz_id !== $session->quiz_id) {
            throw new ConflictException(__('live_quiz.question_not_in_quiz'));
        }
    }

    /**
     * @param  array<int, LiveQuizSessionState>  $allowed
     */
    private function assertState(LiveQuizSession $session, array $allowed, string $messageKey): void
    {
        foreach ($allowed as $state) {
            if ($session->state === $state) {
                return;
            }
        }

        throw new ConflictException(__($messageKey));
    }

    private function assertLaunchable(LiveQuizSession $session): void
    {
        $this->assertState($session, [
            LiveQuizSessionState::Lobby,
            LiveQuizSessionState::QuestionClosed,
            LiveQuizSessionState::Results,
        ], 'live_quiz.invalid_transition');
    }
}
