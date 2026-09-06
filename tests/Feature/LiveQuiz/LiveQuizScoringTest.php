<?php

namespace Tests\Feature\LiveQuiz;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\LiveQuiz\LiveQuizHostService;
use App\Services\LiveQuiz\LiveQuizPlayService;
use App\Services\LiveQuiz\LiveQuizScoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveQuizScoringTest extends TestCase
{
    use LiveQuizFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function correct_speed_bonus_is_deterministic_on_fixed_now(): void
    {
        $offering = $this->offering('LQ-SC1');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering, [
            'time_limit_seconds' => 30,
            'points' => 1000,
        ]);
        $question = $this->firstQuestion($quiz);
        $host = app(LiveQuizHostService::class);
        $play = app(LiveQuizPlayService::class);

        $t0 = Carbon::parse('2026-09-06 12:00:00', 'UTC');
        Carbon::setTestNow($t0);
        $session = $host->startSession($instructor, $quiz);
        $play->join($student, $session->join_code);
        $session = $host->launchQuestion($instructor, $session, $question);

        Carbon::setTestNow($t0->copy()->addSeconds(10));
        $answer = $play->answer($student, $session, $question, $this->correctOption($question)->id);

        $this->assertSame(LiveQuizScoring::correctScore(1000, 30, 20), $answer->score);
        $this->assertSame(1666, $answer->score);
        $this->assertSame($t0->copy()->addSeconds(10)->getTimestamp(), $answer->answered_at->getTimestamp());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'live_quiz.answer',
            'actor_id' => $student->id,
            'entity_id' => $answer->id,
        ]);
        $this->assertSame(1, AuditLog::query()->where('action', 'live_quiz.answer')->count());
    }

    #[Test]
    public function late_and_wrong_answers_score_zero_and_second_answer_conflicts(): void
    {
        $offering = $this->offering('LQ-SC2');
        $instructor = $this->instructorOn($offering);
        $lateStudent = $this->studentOn($offering);
        $wrongStudent = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($wrongStudent, $offering);
        $quiz = $this->readyQuiz($instructor, $offering, [
            'time_limit_seconds' => 30,
            'points' => 1000,
        ]);
        $question = $this->firstQuestion($quiz);
        $host = app(LiveQuizHostService::class);
        $play = app(LiveQuizPlayService::class);

        $t0 = Carbon::parse('2026-09-06 15:00:00', 'UTC');
        Carbon::setTestNow($t0);
        $session = $host->startSession($instructor, $quiz);
        $play->join($lateStudent, $session->join_code);
        $play->join($wrongStudent, $session->join_code);
        $session = $host->launchQuestion($instructor, $session, $question);

        Carbon::setTestNow($t0->copy()->addSeconds(5));
        $wrong = $play->answer($wrongStudent, $session, $question, $this->wrongOption($question)->id);
        $this->assertSame(0, $wrong->score);

        try {
            $play->answer($wrongStudent, $session, $question, $this->correctOption($question)->id);
            $this->fail('second answer should conflict');
        } catch (ConflictException $e) {
            $this->assertSame(__('live_quiz.already_answered'), $e->getMessage());
        }

        Carbon::setTestNow($t0->copy()->addSeconds(31));
        $late = $play->answer($lateStudent, $session, $question, $this->correctOption($question)->id);
        $this->assertSame(0, $late->score);
    }

    #[Test]
    public function a_non_participant_cannot_answer(): void
    {
        $offering = $this->offering('LQ-SC3');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $stranger = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($stranger, $offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $host = app(LiveQuizHostService::class);
        $play = app(LiveQuizPlayService::class);

        $session = $host->startSession($instructor, $quiz);
        $play->join($student, $session->join_code);
        $session = $host->launchQuestion($instructor, $session, $question);

        $this->expectException(AuthorizationException::class);
        $play->answer($stranger, $session, $question, $this->correctOption($question)->id);
    }

    #[Test]
    public function scoring_formula_is_integer_and_clamped(): void
    {
        $this->assertSame(2000, LiveQuizScoring::correctScore(1000, 30, 30));
        $this->assertSame(1000, LiveQuizScoring::correctScore(1000, 30, 0));
        $this->assertSame(2000, LiveQuizScoring::correctScore(1000, 30, 99));
        $this->assertSame(0, LiveQuizScoring::correctScore(0, 30, 15));
    }
}
