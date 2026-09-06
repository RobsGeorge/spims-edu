<?php

namespace Tests\Feature\Feedback;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Services\Feedback\FeedbackSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SurveyScopeTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;

    #[Test]
    public function student_b_cannot_read_or_submit_a_survey_for_offering_a(): void
    {
        $actors = $this->offeringActors('SCP1');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        $studentB = User::factory()->withRole(RoleType::Student)->create();

        $this->asApi($studentB)
            ->getJson(route('api.v1.feedback.surveys.show', $survey))
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->asApi($studentB)
            ->postJson(route('api.v1.feedback.surveys.submit', $survey), [
                'answers' => [$question->id => 'Hijack'],
            ])
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $inbox = $this->asApi($studentB)
            ->getJson(route('api.v1.feedback.surveys.index'))
            ->assertOk()
            ->json('data');
        $ids = array_column($inbox, 'id');
        $this->assertNotContains($survey->id, $ids);
    }

    #[Test]
    public function instructor_cannot_manage_a_survey_on_an_offering_they_do_not_staff(): void
    {
        $mine = $this->offeringActors('SCP2');
        $theirs = $this->offering('SCP3');
        $otherInstructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($otherInstructor, $theirs);

        $foreign = $this->publishedSurvey($otherInstructor, $theirs, 'Foreign eval');

        $this->expectException(AuthorizationException::class);
        app(FeedbackSurveyService::class)->close($mine['instructor'], $foreign);
    }
}
