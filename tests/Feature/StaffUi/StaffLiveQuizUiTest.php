<?php

namespace Tests\Feature\StaffUi;

use App\Models\LiveQuizSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\LiveQuiz\LiveQuizFixtures;
use Tests\TestCase;

class StaffLiveQuizUiTest extends TestCase
{
    use LiveQuizFixtures;
    use RefreshDatabase;

    #[Test]
    public function start_session_shows_join_code_and_student_is_forbidden_on_host_page(): void
    {
        $offering = $this->offering('S8LQ');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $quiz = $this->readyQuiz($instructor, $offering);

        $this->actingAs($instructor)
            ->post(route('teach.live-quiz.start', [$offering, $quiz]))
            ->assertRedirect();

        $session = LiveQuizSession::query()->where('quiz_id', $quiz->id)->firstOrFail();
        $this->assertNotEmpty($session->join_code);

        $this->actingAs($instructor)
            ->get(route('teach.live-quiz.session', [$offering, $session]))
            ->assertOk()
            ->assertSee($session->join_code, false)
            ->assertSee(__('staff.live_quiz.join_code'));

        $this->actingAs($student)
            ->get(route('teach.live-quiz.session', [$offering, $session]))
            ->assertForbidden()
            ->assertDontSee($session->join_code, false);

        Auth::logout();
        Auth::forgetGuards();
        $this->flushSession();

        $guest = $this->get(route('teach.live-quiz.session', [$offering, $session]));
        $guest->assertRedirect(route('auth.login'));
        $this->assertStringNotContainsString($session->join_code, $guest->getContent());
    }
}
