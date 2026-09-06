<?php

namespace Tests\Feature\Api;

use App\Enums\FeedbackQuestionKind;
use App\Models\AuditLog;
use App\Services\Feedback\FeedbackSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Feedback\FeedbackFixtures;
use Tests\TestCase;

class StudentWaveESurveysTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;

    #[Test]
    public function student_can_list_show_and_submit_a_published_survey(): void
    {
        $actors = $this->offeringActors('S6E1');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering'], 'Wave E eval', [
            'prompt' => 'Comments',
            'kind' => FeedbackQuestionKind::Text->value,
        ]);
        $question = $this->firstQuestion($survey);
        $token = $this->apiToken($actors['student']);

        $list = $this->withToken($token)
            ->getJson(route('api.v1.feedback.surveys.index'))
            ->assertOk();
        $this->assertSame($survey->id, $list->json('data.0.id'));
        $this->assertSame('PUBLISHED', $list->json('data.0.status'));
        $this->assertFalse($list->json('data.0.submitted'));

        $this->withToken($token)
            ->getJson(route('api.v1.feedback.surveys.show', $survey))
            ->assertOk()
            ->assertJsonPath('data.title', 'Wave E eval')
            ->assertJsonPath('data.questions.0.id', $question->id);

        Auth::forgetGuards();
        $this->withToken($token)
            ->postJson(route('api.v1.feedback.surveys.submit', $survey), [
                'answers' => [$question->id => 'Helpful'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_anonymous', true)
            ->assertJsonMissingPath('data.student_id');

        $this->assertSame(1, AuditLog::query()->where('action', 'feedback.submit')->count());
    }

    #[Test]
    public function unauthenticated_requests_return_401(): void
    {
        $actors = $this->offeringActors('S6E2');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);

        $this->getJson(route('api.v1.feedback.surveys.index'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson(route('api.v1.feedback.surveys.show', $survey))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson(route('api.v1.feedback.surveys.submit', $survey), ['answers' => []])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function status_label_is_localized_in_arabic(): void
    {
        $actors = $this->offeringActors('S6E3');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);

        $this->asApi($actors['student'])
            ->withHeaders(['Accept-Language' => 'ar'])
            ->getJson(route('api.v1.feedback.surveys.show', $survey))
            ->assertOk()
            ->assertJsonPath('data.status', 'PUBLISHED')
            ->assertJsonPath('data.status_label', __('feedback.status_published', [], 'ar'));

        $this->assertSame('منشور', __('feedback.status_published', [], 'ar'));
        $this->assertNotSame(
            __('feedback.status_published', [], 'en'),
            __('feedback.status_published', [], 'ar')
        );
    }

    #[Test]
    public function unpublished_and_out_of_window_submits_are_422(): void
    {
        $actors = $this->offeringActors('S6E4');
        $draft = $this->draftSurvey($actors['instructor'], $actors['offering'], 'Not open');
        $question = $this->firstQuestion($draft);

        $this->asApi($actors['student'])
            ->postJson(route('api.v1.feedback.surveys.submit', $draft), [
                'answers' => [$question->id => 'Nope'],
            ])
            ->assertStatus(422);

        $windowed = app(FeedbackSurveyService::class)->create($actors['instructor'], [
            'title' => 'Future window',
            'opens_at' => now()->addDay(),
        ], $actors['offering']);
        app(FeedbackSurveyService::class)->addQuestion($actors['instructor'], $windowed, [
            'prompt' => 'Later',
            'kind' => FeedbackQuestionKind::Text->value,
        ]);
        $published = app(FeedbackSurveyService::class)->publish($actors['instructor'], $windowed);
        $windowQuestion = $this->firstQuestion($published);

        $this->asApi($actors['student'])
            ->postJson(route('api.v1.feedback.surveys.submit', $published), [
                'answers' => [$windowQuestion->id => 'Too soon'],
            ])
            ->assertStatus(422);
    }
}
