<?php

namespace Tests\Feature\LiveQuiz;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Services\LiveQuiz\LiveQuizHostService;
use App\Services\LiveQuiz\LiveQuizPlayService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveQuizScopeTest extends TestCase
{
    use LiveQuizFixtures;
    use RefreshDatabase;

    #[Test]
    public function a_student_not_enrolled_in_the_offering_cannot_join(): void
    {
        $offering = $this->offering('LQ-SP1');
        $instructor = $this->instructorOn($offering);
        $outsider = User::factory()->withRole(RoleType::Student)->create();
        $quiz = $this->readyQuiz($instructor, $offering);
        $session = $this->startLobby($instructor, $quiz);

        try {
            app(LiveQuizPlayService::class)->join($outsider, $session->join_code);
            $this->fail('unenrolled join should be forbidden');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        Auth::forgetGuards();
        $this->withToken($outsider->createToken('api', ['role:STUDENT'])->plainTextToken)
            ->postJson(route('api.v1.live-quiz.join'), ['code' => $session->join_code])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    #[Test]
    public function unknown_join_code_is_not_found(): void
    {
        $offering = $this->offering('LQ-SP2');
        $student = $this->studentOn($offering);

        try {
            app(LiveQuizPlayService::class)->join($student, 'NOCODE');
            $this->fail('unknown code should 404');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        Auth::forgetGuards();
        $this->withToken($student->createToken('api', ['role:STUDENT'])->plainTextToken)
            ->postJson(route('api.v1.live-quiz.join'), ['code' => 'NOCODE'])
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    #[Test]
    public function student_b_cannot_answer_as_student_a(): void
    {
        $offering = $this->offering('LQ-SP3');
        $instructor = $this->instructorOn($offering);
        $studentA = $this->studentOn($offering);
        $studentB = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($studentB, $offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $host = app(LiveQuizHostService::class);
        $play = app(LiveQuizPlayService::class);

        $session = $host->startSession($instructor, $quiz);
        $play->join($studentA, $session->join_code);
        $session = $host->launchQuestion($instructor, $session, $question);

        $this->expectException(AuthorizationException::class);
        $play->answer($studentB, $session, $question, $this->correctOption($question)->id);
    }

    #[Test]
    public function student_b_cannot_poll_or_answer_as_a_over_http(): void
    {
        $offering = $this->offering('LQ-SP4');
        $instructor = $this->instructorOn($offering);
        $studentA = $this->studentOn($offering);
        $studentB = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($studentB, $offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $host = app(LiveQuizHostService::class);
        $play = app(LiveQuizPlayService::class);

        $session = $host->startSession($instructor, $quiz);
        $play->join($studentA, $session->join_code);
        $session = $host->launchQuestion($instructor, $session, $question);

        Auth::forgetGuards();
        $tokenB = $studentB->createToken('api', ['role:STUDENT'])->plainTextToken;
        $this->withToken($tokenB)
            ->getJson(route('api.v1.live-quiz.sessions.show', $session))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        Auth::forgetGuards();
        $this->withToken($tokenB)
            ->postJson(route('api.v1.live-quiz.sessions.answer', [$session, $question]), [
                'option_id' => $this->correctOption($question)->id,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    #[Test]
    public function an_instructor_cannot_host_another_offerings_quiz(): void
    {
        $mine = $this->offering('LQ-SP5');
        $theirs = $this->offering('LQ-SP6');
        $instructor = $this->instructorOn($mine);
        $theirInstructor = $this->instructorOn($theirs);
        $quiz = $this->readyQuiz($theirInstructor, $theirs);

        $this->expectException(AuthorizationException::class);
        app(LiveQuizHostService::class)->startSession($instructor, $quiz);
    }
}
