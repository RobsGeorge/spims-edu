<?php

namespace Tests\Feature\Feedback;

use App\Models\AuditLog;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSubmissionIdentity;
use App\Services\Feedback\FeedbackSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\StudentApiFixtures;
use Tests\TestCase;

class SurveySubmitTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function student_can_submit_once_and_answers_are_stored(): void
    {
        $actors = $this->offeringActors('SUB1');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);

        $submission = app(FeedbackSubmissionService::class)->submit($actors['student'], $survey, [
            $question->id => 'Excellent course',
        ]);

        $this->assertTrue($submission->is_anonymous);
        $this->assertNull($submission->getAttribute('student_id'));
        $this->assertSame(1, FeedbackSubmission::query()->where('survey_id', $survey->id)->count());
        $this->assertSame(1, FeedbackAnswer::query()->where('submission_id', $submission->id)->count());
        $this->assertSame('Excellent course', FeedbackAnswer::query()->first()->value);
        $this->assertTrue(
            FeedbackSubmissionIdentity::query()
                ->where('submission_id', $submission->id)
                ->where('student_id', $actors['student']->id)
                ->exists()
        );
        $this->assertSame(1, AuditLog::query()->where('action', 'feedback.submit')->count());
    }

    #[Test]
    public function second_submit_returns_409(): void
    {
        $actors = $this->offeringActors('SUB2');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        $payload = ['answers' => [$question->id => 'First']];

        $this->asApi($actors['student'])
            ->postJson(route('api.v1.feedback.surveys.submit', $survey), $payload)
            ->assertCreated()
            ->assertJsonPath('data.is_anonymous', true);

        $this->asApi($actors['student'])
            ->postJson(route('api.v1.feedback.surveys.submit', $survey), [
                'answers' => [$question->id => 'Second'],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');

        $this->assertSame(1, FeedbackSubmission::query()->count());
        $this->assertSame('First', FeedbackAnswer::query()->first()->value);
    }

    #[Test]
    public function student_get_never_includes_identity_or_student_id(): void
    {
        $actors = $this->offeringActors('SUB3');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        app(FeedbackSubmissionService::class)->submit($actors['student'], $survey, [
            $question->id => 'Anonymous thoughts',
        ]);

        $show = $this->asApi($actors['student'])
            ->getJson(route('api.v1.feedback.surveys.show', $survey))
            ->assertOk();

        $encoded = json_encode($show->json());
        $this->assertStringNotContainsString('"student_id"', $encoded);
        $this->assertStringNotContainsString('"identity"', $encoded);
        $this->assertStringNotContainsString($actors['student']->id, $encoded);
        $this->assertTrue($show->json('data.submitted'));
        $this->assertSame('Anonymous thoughts', $show->json('data.answers.'.$question->id));

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('feedback_submissions');
        $this->assertNotContains('student_id', $columns);
    }
}
