<?php

namespace Tests\Feature\LiveQuiz;

use App\Enums\LiveQuizSessionState;
use App\Models\AuditLog;
use App\Services\LiveQuiz\LiveQuizHostService;
use App\Services\LiveQuiz\LiveQuizPlayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveQuizFallbackTest extends TestCase
{
    use LiveQuizFixtures;
    use RefreshDatabase;

    #[Test]
    public function with_broadcasting_off_poll_matches_state_after_launch_and_does_not_write(): void
    {
        Config::set('broadcasting.default', 'null');
        $this->assertSame('null', config('broadcasting.default'));

        $offering = $this->offering('LQ-FB1');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $host = app(LiveQuizHostService::class);
        $play = app(LiveQuizPlayService::class);

        $session = $host->startSession($instructor, $quiz);
        $play->join($student, $session->join_code);
        $session = $host->launchQuestion($instructor, $session, $question);

        $this->assertSame(LiveQuizSessionState::QuestionOpen, $session->state);
        $auditsBefore = AuditLog::query()->count();
        $updatedBefore = $session->fresh()->updated_at?->toIso8601String();

        Auth::forgetGuards();
        $token = $student->createToken('api', ['role:STUDENT'])->plainTextToken;
        $response = $this->withToken($token)
            ->getJson(route('api.v1.live-quiz.sessions.show', $session));

        $response->assertOk()
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.state', LiveQuizSessionState::QuestionOpen->value)
            ->assertJsonPath('data.current_question.id', $question->id)
            ->assertJsonPath('data.current_question.closes_at', $session->question_closes_at->toIso8601String());

        $options = $response->json('data.current_question.options');
        $this->assertNotEmpty($options);
        foreach ($options as $option) {
            $this->assertArrayNotHasKey('is_correct', $option);
        }

        $this->assertSame($auditsBefore, AuditLog::query()->count());
        $this->assertSame($updatedBefore, $session->fresh()->updated_at?->toIso8601String());
        $this->assertArrayHasKey('server_now', $response->json('data'));
    }
}
