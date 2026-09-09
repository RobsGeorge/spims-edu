<?php

namespace Tests\Feature\Feedback;

use App\Models\FeedbackIdentityRevealRequest;
use App\Models\FeedbackSubmission;
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

    /**
     * Explicit structural-anonymity assertion (Step 10 gate requirement).
     *
     * Anonymous = structurally unlinked, not merely "we don't display it":
     *   1. feedback_submissions carries no student_id column.
     *   2. Querying a submission with its answers via the ORM does not expose
     *      the submitter — the identity relation is sealed in a separate table.
     *   3. The only path to the submitter runs through feedback_submission_identities,
     *      which is a sealed side-table never eager-loaded on student read paths.
     */
    #[Test]
    public function anonymous_submission_is_structurally_unlinked_from_student_identity(): void
    {
        // --- 1. Schema guarantee: no student_id on the submissions table ---
        $this->assertNotContains(
            'student_id',
            Schema::getColumnListing('feedback_submissions'),
            'feedback_submissions must carry no student_id — identity lives in the sealed feedback_submission_identities table'
        );

        // --- 2. ORM guarantee: serialising a submission with answers never leaks the submitter ---
        $actors   = $this->offeringActors('ANON3');
        $survey   = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);

        $submission = app(FeedbackSubmissionService::class)->submit(
            $actors['student'],
            $survey,
            [$question->id => 'Private thought'],
        );

        // Re-fetch exactly as a naïve view or service might — with answers, without identity
        $fresh = FeedbackSubmission::query()
            ->with(['answers', 'survey'])
            ->findOrFail($submission->id);

        $serialized = json_encode($fresh->toArray());

        $this->assertStringNotContainsString(
            '"student_id"',
            $serialized,
            'Serialising a submission with its answers must not contain student_id'
        );
        $this->assertStringNotContainsString(
            $actors['student']->id,
            $serialized,
            'Serialising a submission must not reveal the student ULID anywhere'
        );

        // $hidden on FeedbackSubmission keeps the identity relation out of toArray/toJson
        $this->assertArrayNotHasKey(
            'identity',
            $fresh->toArray(),
            'FeedbackSubmission::$hidden must exclude the identity relation from serialization'
        );

        // --- 3. The sealed link exists — it is retrievable only through the identities table ---
        $this->assertTrue(
            FeedbackSubmissionIdentity::query()
                ->where('submission_id', $submission->id)
                ->where('student_id', $actors['student']->id)
                ->exists(),
            'Identity row must exist in the sealed table — it is sealed, not deleted'
        );

        // A direct query on feedback_submissions alone cannot reveal who submitted
        $directLookup = FeedbackSubmission::query()
            ->where('survey_id', $survey->id)
            ->get();

        foreach ($directLookup as $row) {
            $this->assertNull(
                $row->getAttribute('student_id'),
                'Direct query on feedback_submissions must never return student_id'
            );
        }
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
