<?php

namespace Tests\Feature\LiveQuiz;

use App\Enums\LiveQuizSessionState;
use App\Enums\LiveQuizStatus;
use App\Exceptions\ConflictException;
use App\Models\AuditLog;
use App\Models\LiveQuiz;
use App\Services\LiveQuiz\LiveQuizHostService;
use App\Services\LiveQuiz\LiveQuizPlayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveQuizLifecycleTest extends TestCase
{
    use LiveQuizFixtures;
    use RefreshDatabase;

    #[Test]
    public function start_opens_lobby_and_writes_an_audit_row(): void
    {
        $offering = $this->offering('LQ-LC1');
        $instructor = $this->instructorOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);

        $session = $this->startLobby($instructor, $quiz);

        $this->assertSame(LiveQuizSessionState::Lobby, $session->state);
        $this->assertNotEmpty($session->join_code);
        $this->assertSame(6, strlen($session->join_code));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'live_quiz.session_start',
            'actor_id' => $instructor->id,
            'entity_id' => $session->id,
        ]);
    }

    #[Test]
    public function launch_from_ended_is_conflict(): void
    {
        $offering = $this->offering('LQ-LC2');
        $instructor = $this->instructorOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $session = $this->startLobby($instructor, $quiz);
        $host = app(LiveQuizHostService::class);
        $host->endSession($instructor, $session);

        $this->expectException(ConflictException::class);
        $host->launchQuestion($instructor, $session->fresh(), $this->firstQuestion($quiz));
    }

    #[Test]
    public function answer_in_lobby_is_conflict(): void
    {
        $offering = $this->offering('LQ-LC3');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $session = $this->startLobby($instructor, $quiz);
        $play = app(LiveQuizPlayService::class);
        $play->join($student, $session->join_code);
        $question = $this->firstQuestion($quiz);

        $this->expectException(ConflictException::class);
        $play->answer($student, $session, $question, $this->correctOption($question)->id);
    }

    #[Test]
    public function close_and_results_from_lobby_are_rejected(): void
    {
        $offering = $this->offering('LQ-LC4');
        $instructor = $this->instructorOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $session = $this->startLobby($instructor, $quiz);
        $host = app(LiveQuizHostService::class);

        try {
            $host->closeQuestion($instructor, $session);
            $this->fail('close from LOBBY should conflict');
        } catch (ConflictException) {
            $this->assertTrue(true);
        }

        try {
            $host->showResults($instructor, $session);
            $this->fail('results from LOBBY should conflict');
        } catch (ConflictException) {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function happy_path_lobby_open_close_results_end(): void
    {
        $offering = $this->offering('LQ-LC5');
        $instructor = $this->instructorOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $host = app(LiveQuizHostService::class);
        $session = $host->startSession($instructor, $quiz);
        $question = $this->firstQuestion($quiz);

        $session = $host->launchQuestion($instructor, $session, $question);
        $this->assertSame(LiveQuizSessionState::QuestionOpen, $session->state);
        $this->assertNotNull($session->question_opened_at);
        $this->assertNotNull($session->question_closes_at);
        $this->assertSame(
            $question->time_limit_seconds,
            $session->question_closes_at->getTimestamp() - $session->question_opened_at->getTimestamp()
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'live_quiz.question_launch',
            'entity_id' => $session->id,
        ]);

        $session = $host->closeQuestion($instructor, $session);
        $this->assertSame(LiveQuizSessionState::QuestionClosed, $session->state);

        $session = $host->showResults($instructor, $session);
        $this->assertSame(LiveQuizSessionState::Results, $session->state);

        $session = $host->endSession($instructor, $session);
        $this->assertSame(LiveQuizSessionState::Ended, $session->state);
        $this->assertSame(1, AuditLog::query()->where('action', 'live_quiz.session_start')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'live_quiz.question_launch')->count());
    }

    #[Test]
    public function draft_quiz_cannot_start_and_user_facing_strings_resolve(): void
    {
        $offering = $this->offering('LQ-LC6');
        $instructor = $this->instructorOn($offering);
        $quiz = LiveQuiz::query()->create([
            'offering_id' => $offering->id,
            'title' => 'Empty',
            'status' => LiveQuizStatus::Draft,
            'created_by' => $instructor->id,
        ]);

        try {
            app(LiveQuizHostService::class)->startSession($instructor, $quiz);
            $this->fail('DRAFT start should conflict');
        } catch (ConflictException $e) {
            $this->assertSame(__('live_quiz.quiz_not_ready'), $e->getMessage());
        }

        foreach (['ar', 'en', 'fr'] as $locale) {
            $this->assertNotSame('live_quiz.invalid_transition', __('live_quiz.invalid_transition', [], $locale));
            $this->assertNotSame('live_quiz.not_enrolled', __('live_quiz.not_enrolled', [], $locale));
        }
    }
}
