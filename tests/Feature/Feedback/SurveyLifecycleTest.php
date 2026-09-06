<?php

namespace Tests\Feature\Feedback;

use App\Models\AuditLog;
use App\Services\Feedback\FeedbackSubmissionService;
use App\Services\Feedback\FeedbackSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\StudentApiFixtures;
use Tests\TestCase;

class SurveyLifecycleTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function draft_surveys_are_not_listed_to_students(): void
    {
        $actors = $this->offeringActors('LIFE1');
        $draft = $this->draftSurvey($actors['instructor'], $actors['offering'], 'Draft eval');

        $inbox = app(FeedbackSurveyService::class)->inboxFor($actors['student']);
        $ids = array_map(fn ($survey) => $survey->id, $inbox);

        $this->assertNotContains($draft->id, $ids);

        $this->asApi($actors['student'])
            ->getJson(route('api.v1.feedback.surveys.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $draft->id]);

        $this->asApi($actors['student'])
            ->getJson(route('api.v1.feedback.surveys.show', $draft))
            ->assertNotFound();
    }

    #[Test]
    public function publish_shows_the_survey_and_close_rejects_submit(): void
    {
        $actors = $this->offeringActors('LIFE2');
        $service = app(FeedbackSurveyService::class);
        $survey = $this->draftSurvey($actors['instructor'], $actors['offering'], 'Published eval');

        $published = $service->publish($actors['instructor'], $survey);
        $this->assertTrue($published->isPublished());
        $this->assertSame(1, AuditLog::query()->where('action', 'feedback.publish')->count());

        $inboxIds = array_map(fn ($row) => $row->id, $service->inboxFor($actors['student']));
        $this->assertContains($published->id, $inboxIds);

        $this->asApi($actors['student'])
            ->getJson(route('api.v1.feedback.surveys.index'))
            ->assertOk()
            ->assertJsonFragment(['id' => $published->id, 'status' => 'PUBLISHED']);

        $closed = $service->close($actors['instructor'], $published->fresh());
        $this->assertTrue($closed->isClosed());

        $question = $this->firstQuestion($closed);
        $this->asApi($actors['student'])
            ->postJson(route('api.v1.feedback.surveys.submit', $closed), [
                'answers' => [$question->id => 'Too late'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FeedbackSubmissionService::class)->submit($actors['student'], $closed->fresh(), [
            $question->id => 'Too late',
        ]);
    }
}
