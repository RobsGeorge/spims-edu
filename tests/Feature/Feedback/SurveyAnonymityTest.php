<?php

namespace Tests\Feature\Feedback;

use App\Models\FeedbackIdentityRevealRequest;
use App\Models\FeedbackSubmissionIdentity;
use App\Services\Feedback\FeedbackIdentityRevealService;
use App\Services\Feedback\FeedbackReportService;
use App\Services\Feedback\FeedbackSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SurveyAnonymityTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;

    #[Test]
    public function report_has_no_student_ids_while_identity_row_exists(): void
    {
        $actors = $this->offeringActors('ANON1');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        $submission = app(FeedbackSubmissionService::class)->submit($actors['student'], $survey, [
            $question->id => 'Keep me anonymous',
        ]);

        $this->assertTrue(Schema::hasTable('feedback_submission_identities'));
        $this->assertTrue(
            FeedbackSubmissionIdentity::query()
                ->where('submission_id', $submission->id)
                ->where('student_id', $actors['student']->id)
                ->exists()
        );

        $report = app(FeedbackReportService::class);
        $aggregates = $report->aggregatesByQuestion($actors['instructor'], $survey);
        $rows = $report->submissions($actors['instructor'], $survey);

        $encoded = json_encode(['aggregates' => $aggregates, 'submissions' => $rows]);
        $this->assertStringNotContainsString('"student_id"', $encoded);
        $this->assertStringNotContainsString($actors['student']->id, $encoded);
        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('student_id', $rows[0]);
        $this->assertTrue($rows[0]['is_anonymous']);
    }

    #[Test]
    public function pending_reveal_does_not_leak_identity_in_the_report(): void
    {
        $actors = $this->offeringActors('ANON2');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        $submission = app(FeedbackSubmissionService::class)->submit($actors['student'], $survey, [
            $question->id => 'Still sealed',
        ]);

        app(FeedbackIdentityRevealService::class)->request(
            $actors['instructor'],
            $submission,
            'Need to follow up'
        );

        $this->assertSame(1, FeedbackIdentityRevealRequest::query()->where('status', 'PENDING')->count());

        $rows = app(FeedbackReportService::class)->submissions($actors['instructor'], $survey);
        $this->assertArrayNotHasKey('student_id', $rows[0]);
        $this->assertStringNotContainsString($actors['student']->id, json_encode($rows));
    }
}
