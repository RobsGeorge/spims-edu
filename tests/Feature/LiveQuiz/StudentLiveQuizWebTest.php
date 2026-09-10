<?php

namespace Tests\Feature\LiveQuiz;

use App\Enums\LiveQuizSessionState;
use App\Services\LiveQuiz\LiveQuizHostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentLiveQuizWebTest extends TestCase
{
    use LiveQuizFixtures;
    use RefreshDatabase;

    #[Test]
    public function student_joins_with_code_sees_question_answers_once_and_second_answer_is_conflict(): void
    {
        $offering = $this->offering('WEB-LQ1');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $other = $this->offering('WEB-LQ2');
        $otherInstructor = $this->instructorOn($other);
        $otherQuiz = $this->readyQuiz($otherInstructor, $other);
        $otherSession = $this->startLobby($otherInstructor, $otherQuiz);
        $session = $this->startLobby($instructor, $quiz);

        $this->actingAs($student)
            ->get(route('live-quiz.join'))
            ->assertOk()
            ->assertSee(__('live_quiz.join_title'), false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('name="code"', false)
            ->assertDontSee($session->join_code, false)
            ->assertDontSee($otherSession->join_code, false)
            ->assertDontSee(__('staff.live_quiz.launch'), false)
            ->assertDontSee(__('staff.live_quiz.close_question'), false);

        $this->actingAs($student)
            ->post(route('live-quiz.join.store'), ['code' => $session->join_code])
            ->assertRedirect(route('live-quiz.sessions.show', $session));

        $lobby = $this->actingAs($student)
            ->get(route('live-quiz.sessions.show', $session));
        $lobby->assertOk()
            ->assertSee(__('live_quiz.state_LOBBY'), false)
            ->assertSee(__('live_quiz.waiting_lobby'), false)
            ->assertSee('http-equiv="refresh"', false)
            ->assertSee('content="2"', false)
            ->assertDontSee($otherSession->join_code, false)
            ->assertDontSee(__('staff.live_quiz.launch'), false)
            ->assertDontSee(__('staff.live_quiz.close_question'), false)
            ->assertDontSee(__('staff.live_quiz.end'), false)
            ->assertDontSee('spims-live-quiz-launch', false);

        $session = app(LiveQuizHostService::class)->launchQuestion($instructor, $session, $question);

        $open = $this->actingAs($student)
            ->get(route('live-quiz.sessions.show', $session));
        $open->assertOk()
            ->assertSee(__('live_quiz.state_QUESTION_OPEN'), false)
            ->assertSee($question->prompt, false)
            ->assertSee('name="option_id"', false)
            ->assertSee($this->correctOption($question)->label, false)
            ->assertSee($this->wrongOption($question)->label, false)
            ->assertSee(__('live_quiz.submit_answer'), false)
            ->assertDontSee($otherSession->join_code, false)
            ->assertDontSee(__('staff.live_quiz.launch'), false);

        $this->actingAs($student)
            ->post(route('live-quiz.sessions.answer', [$session, $question]), [
                'option_id' => $this->correctOption($question)->id,
            ])
            ->assertRedirect(route('live-quiz.sessions.show', $session));

        $waiting = $this->actingAs($student)
            ->get(route('live-quiz.sessions.show', $session));
        $waiting->assertOk()
            ->assertSee(__('live_quiz.waiting_answered'), false)
            ->assertDontSee('name="option_id"', false)
            ->assertDontSee(__('live_quiz.submit_answer'), false);

        $this->actingAs($student)
            ->from(route('live-quiz.sessions.show', $session))
            ->post(route('live-quiz.sessions.answer', [$session, $question]), [
                'option_id' => $this->wrongOption($question)->id,
            ])
            ->assertRedirect(route('live-quiz.sessions.show', $session))
            ->assertSessionHas('error', __('live_quiz.already_answered'));

        $this->assertSame(1, $session->answers()->where('question_id', $question->id)->count());
        $this->assertSame(LiveQuizSessionState::QuestionOpen, $session->fresh()->state);
    }

    #[Test]
    public function guests_are_redirected_to_login_and_do_not_see_session_codes(): void
    {
        $offering = $this->offering('WEB-LQ3');
        $instructor = $this->instructorOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $session = $this->startLobby($instructor, $quiz);

        Auth::logout();
        Auth::forgetGuards();
        $this->flushSession();

        $join = $this->get(route('live-quiz.join'));
        $join->assertRedirect(route('auth.login'));
        $this->assertStringNotContainsString($session->join_code, $join->getContent());

        $play = $this->get(route('live-quiz.sessions.show', $session));
        $play->assertRedirect(route('auth.login'));
        $this->assertStringNotContainsString($session->join_code, $play->getContent());

        $post = $this->post(route('live-quiz.join.store'), ['code' => $session->join_code]);
        $post->assertRedirect(route('auth.login'));
        $this->assertStringNotContainsString($session->join_code, $post->getContent());

        $answer = $this->post(route('live-quiz.sessions.answer', [$session, $question]), [
            'option_id' => $this->correctOption($question)->id,
        ]);
        $answer->assertRedirect(route('auth.login'));
        $this->assertStringNotContainsString($session->join_code, $answer->getContent());
    }

    #[Test]
    public function student_cannot_open_host_session_route(): void
    {
        $offering = $this->offering('WEB-LQ4');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $session = $this->startLobby($instructor, $quiz);

        $this->actingAs($student)
            ->get(route('teach.live-quiz.session', [$offering, $session]))
            ->assertForbidden()
            ->assertDontSee($session->join_code, false)
            ->assertDontSee(__('staff.live_quiz.launch'), false);
    }

    #[Test]
    public function learning_hub_and_live_index_link_to_student_join(): void
    {
        $offering = $this->offering('WEB-LQ5');
        $student = $this->studentOn($offering);

        $this->actingAs($student)
            ->get(route('hubs.learning'))
            ->assertOk()
            ->assertSee(__('hubs.live_quiz'), false)
            ->assertSee(route('live-quiz.join'), false);

        $this->actingAs($student)
            ->get(route('live.index'))
            ->assertOk()
            ->assertSee(__('live.live_quiz'), false)
            ->assertSee(route('live-quiz.join'), false);
    }

    #[Test]
    public function state_endpoint_returns_json_without_join_code(): void
    {
        $offering = $this->offering('WEB-LQ6');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $session = $this->startLobby($instructor, $quiz);

        $this->actingAs($student)
            ->post(route('live-quiz.join.store'), ['code' => $session->join_code]);

        $resp = $this->actingAs($student)
            ->getJson(route('live-quiz.sessions.state', $session));

        $resp->assertOk()
            ->assertJsonStructure(['state', 'server_now', 'participant_count', 'quiz', 'you'])
            ->assertJsonMissing(['join_code' => $session->join_code])
            ->assertJsonPath('state', 'LOBBY')
            ->assertJsonPath('participant_count', 1);

        $this->assertArrayNotHasKey('join_code', $resp->json());
    }

    #[Test]
    public function state_endpoint_carries_closes_at_when_question_open(): void
    {
        $offering = $this->offering('WEB-LQ7');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $session = $this->startLobby($instructor, $quiz);

        $this->actingAs($student)
            ->post(route('live-quiz.join.store'), ['code' => $session->join_code]);

        app(LiveQuizHostService::class)->launchQuestion($instructor, $session, $question);

        $resp = $this->actingAs($student)
            ->getJson(route('live-quiz.sessions.state', $session));

        $resp->assertOk()
            ->assertJsonPath('state', 'QUESTION_OPEN')
            ->assertJsonStructure(['current_question' => ['id', 'closes_at', 'time_limit_seconds']]);

        $this->assertNotNull($resp->json('current_question.closes_at'));
        $this->assertNotNull($resp->json('server_now'));
        $this->assertArrayNotHasKey('join_code', $resp->json());
    }

    #[Test]
    public function state_endpoint_is_forbidden_for_non_participants(): void
    {
        $offering = $this->offering('WEB-LQ8');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $session = $this->startLobby($instructor, $quiz);

        // student has NOT joined — must be forbidden
        $this->actingAs($student)
            ->getJson(route('live-quiz.sessions.state', $session))
            ->assertForbidden();
    }

    #[Test]
    public function ended_session_shows_ended_state_not_error(): void
    {
        $offering = $this->offering('WEB-LQ9');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);
        $question = $this->firstQuestion($quiz);
        $session = $this->startLobby($instructor, $quiz);

        $this->actingAs($student)
            ->post(route('live-quiz.join.store'), ['code' => $session->join_code]);

        app(LiveQuizHostService::class)->launchQuestion($instructor, $session, $question);
        app(LiveQuizHostService::class)->closeQuestion($instructor, $session);
        app(LiveQuizHostService::class)->endSession($instructor, $session);

        $this->actingAs($student)
            ->get(route('live-quiz.sessions.show', $session))
            ->assertOk()
            ->assertSee(__('live_quiz.ended'), false)
            ->assertDontSee('JS error', false);
    }
}
