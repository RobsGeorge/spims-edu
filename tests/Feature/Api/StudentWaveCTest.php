<?php

namespace Tests\Feature\Api;

use App\Enums\AssessmentMode;
use App\Enums\AttemptStatus;
use App\Enums\OfferingMode;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\DiscussionBoard;
use App\Models\User;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\QuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentWaveCTest extends TestCase
{
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function assignment_submit_is_idempotent_on_replay(): void
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $bundle = $this->playerBundle('ASG1');
        $assignment = $this->assignmentOn($bundle['offering']);
        $token = $this->apiToken($bundle['student']);

        $first = $this->withToken($token)
            ->post(route('api.v1.assignments.submit', $assignment), [
                'text_body' => 'First draft',
                'file' => UploadedFile::fake()->create('essay.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json', 'Idempotency-Key' => 'submit-1'])
            ->assertCreated();

        $second = $this->withToken($token)
            ->post(route('api.v1.assignments.submit', $assignment), [
                'text_body' => 'Should not apply',
            ], ['Accept' => 'application/json', 'Idempotency-Key' => 'submit-1'])
            ->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, AssignmentSubmission::query()->where('assignment_id', $assignment->id)->count());
        $this->assertSame('First draft', AssignmentSubmission::query()->first()->text_body);
        $this->assertSame(1, AssignmentSubmission::query()->first()->attempt_no);
    }

    #[Test]
    public function unpublished_assignment_cannot_be_submitted(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $bundle = $this->playerBundle('HID1');
        $assignment = $this->assignmentOn($bundle['offering'], released: false);

        $this->withToken($this->apiToken($bundle['student']))
            ->post(route('api.v1.assignments.submit', $assignment), [
                'text_body' => 'Nope',
            ], ['Accept' => 'application/json'])
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    #[Test]
    public function unpublished_assessment_cannot_be_started(): void
    {
        $bundle = $this->playerBundle('HID2');
        $assessment = Assessment::query()->create([
            'offering_id' => $bundle['offering']->id,
            'title' => 'Hidden quiz',
            'mode' => AssessmentMode::Quiz,
            'released' => false,
            'attempts_allowed' => 1,
            'max_points' => 10,
        ]);

        $this->withToken($this->apiToken($bundle['student']))
            ->postJson(route('api.v1.assessments.start', $assessment))
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->assertSame(0, $assessment->attempts()->count());
    }

    #[Test]
    public function resubmit_returns_422_when_disallowed(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $bundle = $this->playerBundle('ASG2');
        $assignment = $this->assignmentOn($bundle['offering'], allowResubmission: false);
        $token = $this->apiToken($bundle['student']);

        $this->withToken($token)
            ->post(route('api.v1.assignments.submit', $assignment), [
                'text_body' => 'Once',
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->withToken($token)
            ->post(route('api.v1.assignments.resubmit', $assignment), [
                'text_body' => 'Twice',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    #[Test]
    public function assessment_start_save_submit_and_timer(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $bundle = $this->playerBundle('EX1');
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $bundle['offering']);

        $bank = app(QuestionBankService::class)->createBank($instructor, $bundle['offering']->course, 'Bank');
        $q = app(QuestionBankService::class)->addQuestion($instructor, $bank, [
            'type' => QuestionType::TrueFalse->value,
            'prompt' => 'Sky is blue',
            'points' => 10,
            'options' => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => false],
            ],
        ]);
        $assessment = app(AssessmentService::class)->create($instructor, $bundle['offering'], [
            'title' => 'Quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 20,
            'attempts_allowed' => 2,
            'max_points' => 10,
        ]);
        app(AssessmentService::class)->attachQuestion($instructor, $assessment, $q);
        $assessment->update(['released' => true]);

        $token = $this->apiToken($bundle['student']);
        $start = $this->withToken($token)
            ->postJson(route('api.v1.assessments.start', $assessment))
            ->assertCreated();
        $attemptId = $start->json('data.attempt_id');
        $this->assertNotEmpty($start->json('data.questions'));
        $this->assertNotEmpty($start->json('data.due_at'));

        $correct = $q->options()->where('is_correct', true)->first();
        $this->withToken($token)
            ->postJson(route('api.v1.attempts.save', $attemptId), [
                'answers' => [$q->id => ['option_id' => $correct->id]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AttemptStatus::InProgress->value);

        $this->withToken($token)
            ->getJson(route('api.v1.attempts.timer', $attemptId))
            ->assertOk()
            ->assertJsonPath('data.status', AttemptStatus::InProgress->value);
        $this->assertGreaterThan(0, $this->withToken($token)
            ->getJson(route('api.v1.attempts.timer', $attemptId))
            ->json('data.remaining_seconds'));

        $this->withToken($token)
            ->postJson(route('api.v1.attempts.submit', $attemptId), [], [
                'Idempotency-Key' => 'attempt-submit-1',
            ])
            ->assertOk();

        $replay = $this->withToken($token)
            ->postJson(route('api.v1.attempts.submit', $attemptId), [], [
                'Idempotency-Key' => 'attempt-submit-1',
            ])
            ->assertOk();
        $this->assertSame($attemptId, $replay->json('data.id'));
    }

    #[Test]
    public function focus_loss_can_terminate_with_423(): void
    {
        config(['assessment.proctor_termination_threshold' => 1]);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $bundle = $this->playerBundle('PRC1');
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $bundle['offering']);
        $bank = app(QuestionBankService::class)->createBank($instructor, $bundle['offering']->course, 'P');
        $essay = app(QuestionBankService::class)->addQuestion($instructor, $bank, [
            'type' => QuestionType::Essay->value,
            'prompt' => 'Explain',
            'points' => 25,
        ]);
        $assessment = app(AssessmentService::class)->create($instructor, $bundle['offering'], [
            'title' => 'Proctored',
            'mode' => AssessmentMode::Exam->value,
            'time_limit_minutes' => 30,
            'attempts_allowed' => 1,
            'max_points' => 25,
        ]);
        app(AssessmentService::class)->attachQuestion($instructor, $assessment, $essay);
        $assessment->update(['released' => true]);

        $token = $this->apiToken($bundle['student']);
        $attemptId = $this->withToken($token)
            ->postJson(route('api.v1.assessments.start', $assessment))
            ->json('data.attempt_id');

        $this->withToken($token)
            ->postJson(route('api.v1.attempts.focus-loss', $attemptId))
            ->assertStatus(423)
            ->assertJsonPath('code', 'LOCKED');
    }

    #[Test]
    public function discussion_get_does_not_create_a_board_and_post_works_when_allowed(): void
    {
        $student = $this->student();
        $offering = $this->offering('DISC', OfferingMode::Cohort);
        $this->enroll($student, $offering);
        $token = $this->apiToken($student);

        $this->assertDatabaseCount('discussion_boards', 0);
        $before = AuditLog::query()->count();

        $this->withToken($token)
            ->getJson(route('api.v1.offerings.discussions', $offering))
            ->assertOk();

        $this->assertDatabaseCount('discussion_boards', 0);
        $this->assertSame($before, AuditLog::query()->count());
        $this->assertDatabaseMissing('discussion_boards', ['offering_id' => $offering->id]);

        $threadId = $this->withToken($token)
            ->postJson(route('api.v1.offerings.discussions.threads.store', $offering), [
                'title' => 'Hello',
                'body' => 'First post',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertNotNull(DiscussionBoard::query()->where('offering_id', $offering->id)->first());

        $this->withToken($token)
            ->postJson(route('api.v1.discussions.threads.posts', $threadId), [
                'body' => 'A reply',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'A reply');
    }
}
