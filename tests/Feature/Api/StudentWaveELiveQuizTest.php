<?php

namespace Tests\Feature\Api;

use App\Enums\LiveQuizSessionState;
use App\Models\AuditLog;
use App\Services\LiveQuiz\LiveQuizHostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\LiveQuiz\LiveQuizFixtures;
use Tests\TestCase;

class StudentWaveELiveQuizTest extends TestCase
{
    use LiveQuizFixtures;
    use RefreshDatabase;
    use StudentApiFixtures {
        LiveQuizFixtures::offering insteadof StudentApiFixtures;
        LiveQuizFixtures::enroll insteadof StudentApiFixtures;
    }

    #[Test]
    public function join_poll_lobby_launch_poll_question_answer_poll(): void
    {
        $offering = $this->offering('S6E-LQ');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $host = app(LiveQuizHostService::class);
        $session = $host->startSession($instructor, $quiz);

        $join = $this->asApi($student)
            ->postJson(route('api.v1.live-quiz.join'), ['code' => $session->join_code])
            ->assertCreated()
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.state', LiveQuizSessionState::Lobby->value);

        $this->assertNull($join->json('data.current_question'));

        $this->asApi($student)
            ->getJson(route('api.v1.live-quiz.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.state', LiveQuizSessionState::Lobby->value)
            ->assertJsonPath('data.current_question', null);

        $session = $host->launchQuestion($instructor, $session, $question);

        $pollOpen = $this->asApi($student)
            ->getJson(route('api.v1.live-quiz.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.state', LiveQuizSessionState::QuestionOpen->value)
            ->assertJsonPath('data.current_question.id', $question->id);

        foreach ($pollOpen->json('data.current_question.options') as $option) {
            $this->assertArrayNotHasKey('is_correct', $option);
        }

        $answer = $this->asApi($student)
            ->postJson(route('api.v1.live-quiz.sessions.answer', [$session, $question]), [
                'option_id' => $this->correctOption($question)->id,
                'answered_at' => '1999-01-01T00:00:00Z',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame($question->id, $answer['question_id']);
        $this->assertGreaterThan(0, $answer['score']);

        $this->asApi($student)
            ->getJson(route('api.v1.live-quiz.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.state', LiveQuizSessionState::QuestionOpen->value)
            ->assertJsonPath('data.you.answered', true)
            ->assertJsonPath('data.you.score', $answer['score']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'live_quiz.session_start',
            'actor_id' => $instructor->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'live_quiz.question_launch',
            'actor_id' => $instructor->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'live_quiz.answer',
            'actor_id' => $student->id,
        ]);
        $this->assertSame(1, AuditLog::query()->where('action', 'live_quiz.answer')->count());
    }

    #[Test]
    public function host_api_start_launch_results_end_are_reachable(): void
    {
        $offering = $this->offering('S6E-HOST');
        $instructor = $this->instructorOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);

        $start = $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.live-quiz.host.start', $quiz))
            ->assertCreated()
            ->assertJsonPath('data.state', LiveQuizSessionState::Lobby->value);

        $sessionId = $start->json('data.id');
        $this->assertNotEmpty($start->json('data.join_code'));

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.live-quiz.sessions.launch', $sessionId), [
                'question_id' => $question->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.state', LiveQuizSessionState::QuestionOpen->value);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.live-quiz.sessions.close', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.state', LiveQuizSessionState::QuestionClosed->value);

        $results = $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.live-quiz.sessions.results', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.state', LiveQuizSessionState::Results->value);

        foreach ($results->json('data.current_question.options') as $option) {
            $this->assertArrayHasKey('is_correct', $option);
        }

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.live-quiz.sessions.end', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.state', LiveQuizSessionState::Ended->value);
    }
}
