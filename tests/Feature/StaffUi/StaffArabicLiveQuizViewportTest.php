<?php

namespace Tests\Feature\StaffUi;

use App\Models\LiveQuizSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\LiveQuiz\LiveQuizFixtures;
use Tests\TestCase;

class StaffArabicLiveQuizViewportTest extends TestCase
{
    use LiveQuizFixtures;
    use RefreshDatabase;
    use StaffArabicViewportAssertions;

    #[Test]
    public function arabic_live_quiz_host_keeps_the_join_code_ltr(): void
    {
        $offering = $this->offering('ARLQ');
        $instructor = $this->arabic($this->instructorOn($offering));
        $quiz = $this->readyQuiz($instructor, $offering);

        $this->actingAs($instructor)
            ->post(route('teach.live-quiz.start', [$offering, $quiz]))
            ->assertRedirect();

        $session = LiveQuizSession::query()->where('quiz_id', $quiz->id)->firstOrFail();

        $page = $this->actingAs($instructor)
            ->get(route('teach.live-quiz.session', [$offering, $session]));
        $this->assertArabicShell($page);
        $page->assertSee(__('staff.live_quiz.join_code'), false)
            ->assertSee(__('staff.live_quiz.console'), false)
            ->assertSee('spims-join-code', false)
            ->assertSee('spims-live-quiz-launch', false)
            ->assertSee($session->join_code, false);

        $this->assertMatchesRegularExpression(
            '/class="display-5 fw-bold spims-join-code" dir="ltr">'.preg_quote($session->join_code, '/').'/',
            $page->getContent(),
        );
    }
}
