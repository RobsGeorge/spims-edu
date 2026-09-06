<?php

namespace Tests\Feature\Feedback;

use App\Enums\FeedbackIdentityRevealStatus;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Feedback\FeedbackIdentityRevealService;
use App\Services\Feedback\FeedbackReportService;
use App\Services\Feedback\FeedbackSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\StudentApiFixtures;
use Tests\TestCase;

class IdentityRevealTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function super_admin_approve_allows_report_to_include_identity(): void
    {
        $actors = $this->offeringActors('REV1');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        $submission = app(FeedbackSubmissionService::class)->submit($actors['student'], $survey, [
            $question->id => 'Please identify me after approval',
        ]);

        $reveal = app(FeedbackIdentityRevealService::class);
        $request = $reveal->request($actors['instructor'], $submission, 'Safety follow-up');
        $this->assertTrue($request->isPending());
        $this->assertSame(1, AuditLog::query()->where('action', 'feedback.identity.request')->count());

        $super = User::factory()->withRole(RoleType::SuperAdmin)->create();
        $approved = $reveal->decide($super, $request, true, 'Approved for follow-up');
        $this->assertSame(FeedbackIdentityRevealStatus::Approved, $approved->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'feedback.identity.reveal')->count());

        $rows = app(FeedbackReportService::class)->submissions($actors['instructor'], $survey);
        $this->assertSame($actors['student']->id, $rows[0]['student_id']);
    }

    #[Test]
    public function deny_keeps_identity_sealed(): void
    {
        $actors = $this->offeringActors('REV2');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        $submission = app(FeedbackSubmissionService::class)->submit($actors['student'], $survey, [
            $question->id => 'Stay sealed',
        ]);

        $reveal = app(FeedbackIdentityRevealService::class);
        $request = $reveal->request($actors['instructor'], $submission);
        $super = User::factory()->withRole(RoleType::SuperAdmin)->create();
        $denied = $reveal->decide($super, $request, false, 'Insufficient grounds');

        $this->assertSame(FeedbackIdentityRevealStatus::Denied, $denied->status);

        $rows = app(FeedbackReportService::class)->submissions($actors['instructor'], $survey);
        $this->assertArrayNotHasKey('student_id', $rows[0]);
        $this->assertStringNotContainsString($actors['student']->id, json_encode($rows));
    }

    #[Test]
    public function student_cannot_request_or_reveal(): void
    {
        $actors = $this->offeringActors('REV3');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        $submission = app(FeedbackSubmissionService::class)->submit($actors['student'], $survey, [
            $question->id => 'Not mine to unseal',
        ]);

        $reveal = app(FeedbackIdentityRevealService::class);

        try {
            $reveal->request($actors['student'], $submission);
            $this->fail('Expected student identity request to be forbidden.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $staffRequest = $reveal->request($actors['instructor'], $submission);

        try {
            $reveal->decide($actors['student'], $staffRequest, true);
            $this->fail('Expected student identity reveal to be forbidden.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        try {
            $reveal->decide($actors['instructor'], $staffRequest, true);
            $this->fail('Expected instructor identity reveal to be forbidden.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertTrue($staffRequest->fresh()->isPending());
    }
}
