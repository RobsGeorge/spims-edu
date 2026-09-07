<?php

namespace Tests\Feature\Api;

use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Enums\OfferingClosingStatus;
use App\Enums\OfferingStaffRole;
use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectGradingMode;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\ProjectAssessment;
use App\Models\User;
use App\Services\Communications\AnnouncementService;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorHardeningTest extends TestCase
{
    use InstructorApiFixtures {
        InstructorApiFixtures::asApi insteadof InstructorGradingFixtures;
        InstructorApiFixtures::apiToken insteadof InstructorGradingFixtures;
        InstructorApiFixtures::offering insteadof InstructorGradingFixtures;
        InstructorApiFixtures::enroll insteadof InstructorGradingFixtures;
    }
    use InstructorGradingFixtures;
    use RefreshDatabase;

    #[Test]
    public function confirmation_token_is_stable_across_repeated_gets(): void
    {
        $bundle = $this->gradingBundle('S8H1');

        $first = $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->getJson(route('api.v1.teach.offerings.gradebook', $bundle['offering']))
            ->assertOk()
            ->json('data.confirmation.confirmation_token');

        $second = $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->getJson(route('api.v1.teach.offerings.gradebook', $bundle['offering']))
            ->assertOk()
            ->json('data.confirmation.confirmation_token');

        $this->assertIsString($first);
        $this->assertSame(32, strlen($first));
        $this->assertSame($first, $second);
    }

    #[Test]
    public function attendance_mark_replay_with_the_same_key_is_not_a_conflict(): void
    {
        $offering = $this->offering('S8H2');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'Retry',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $body = [
            'lock_version' => 0,
            'marks' => [['student_id' => $student->id, 'status' => AttendanceStatus::Present->value]],
        ];

        $first = $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/attendance', $body, [
                'Idempotency-Key' => 'mark-h2',
            ])
            ->assertOk();

        $replay = $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/attendance', $body, [
                'Idempotency-Key' => 'mark-h2',
            ])
            ->assertOk();

        $this->assertSame($first->json('data.lock_version'), $replay->json('data.lock_version'));
        $this->assertSame(1, $session->fresh()->lock_version);
    }

    #[Test]
    public function lock_replay_with_the_same_key_returns_the_original_success(): void
    {
        $bundle = $this->gradingBundle('S8H3');
        $token = $this->lockToken($bundle['instructor'], $bundle['offering']);

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => $token,
            ], ['Idempotency-Key' => 'lock-h3'])
            ->assertOk()
            ->assertJsonPath('data.locked', true);

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => $token,
            ], ['Idempotency-Key' => 'lock-h3'])
            ->assertOk()
            ->assertJsonPath('data.locked', true);

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => $token,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['confirmation']]);
    }

    #[Test]
    public function close_replay_with_the_same_key_does_not_require_a_fresh_token(): void
    {
        $world = $this->staffTwoOfferings();
        $this->announceReady($world['admin'], $world['offeringA'], $world['studentA']);

        $token = $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->getJson('/api/v1/teach/offerings/'.$world['offeringA']->id)
            ->assertOk()
            ->json('data.confirmation')['offering.close']['confirmation_token'];

        $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->postJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/close', [
                'confirmation' => $token,
            ], ['Idempotency-Key' => 'close-h4'])
            ->assertOk()
            ->assertJsonPath('data.status', OfferingClosingStatus::Closed->value);

        $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->postJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/close', [
                'confirmation' => $token,
            ], ['Idempotency-Key' => 'close-h4'])
            ->assertOk()
            ->assertJsonPath('data.status', OfferingClosingStatus::Closed->value);
    }

    #[Test]
    public function remind_with_the_same_key_writes_one_audit_row(): void
    {
        $bundle = $this->gradingBundle('S8H5');

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.assignments.remind-unsubmitted', $bundle['assignment']), [], [
                'Idempotency-Key' => 'remind-h5',
            ])
            ->assertOk();

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.assignments.remind-unsubmitted', $bundle['assignment']), [], [
                'Idempotency-Key' => 'remind-h5',
            ])
            ->assertOk();

        $this->assertSame(1, AuditLog::query()->where('action', 'assignments.remind')->count());
    }

    #[Test]
    public function publish_replay_with_the_same_key_is_not_already_published(): void
    {
        $offering = $this->offering('S8H6');
        $instructor = $this->instructorOn($offering);
        $draft = app(AnnouncementService::class)->draft($instructor, $offering, [
            'title' => 'Retry publish',
            'body' => 'Body',
        ]);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/announcements/'.$draft->id.'/publish', [], [
                'Idempotency-Key' => 'pub-h6',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'PUBLISHED');

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/announcements/'.$draft->id.'/publish', [], [
                'Idempotency-Key' => 'pub-h6',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'PUBLISHED');
    }

    #[Test]
    public function sessions_list_returns_304_when_etag_matches(): void
    {
        $offering = $this->offering('S8H7');
        $instructor = $this->instructorOn($offering);
        app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'ETag',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $first = $this->asApi($instructor, 'INSTRUCTOR')
            ->getJson('/api/v1/teach/offerings/'.$offering->id.'/sessions')
            ->assertOk();

        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/teach/offerings/'.$offering->id.'/sessions')
            ->assertStatus(304);
    }

    #[Test]
    public function ta_is_denied_lock_announce_results_and_project_announce(): void
    {
        $bundle = $this->gradingBundle('S8H8');
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $bundle['offering'], OfferingStaffRole::Ta);
        $attempt = $this->submittedAttempt($bundle['instructor'], $bundle['offering'], $bundle['student']);

        $this->asApi($ta, 'TA')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->asApi($ta, 'TA')
            ->postJson(route('api.v1.teach.assessments.announce-results', $attempt['assessment']), [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $projectAssessment = ProjectAssessment::query()->create([
            'offering_id' => $bundle['offering']->id,
            'title' => 'TA deny',
            'team_size_min' => 1,
            'team_size_max' => 2,
            'join_opens_at' => now()->subHour(),
            'join_closes_at' => now()->addHour(),
            'allow_leave_once' => true,
            'grading_mode' => ProjectGradingMode::Rubric,
            'max_points' => 100,
            'status' => ProjectAssessmentStatus::Published,
        ]);

        $this->asApi($ta, 'TA')
            ->postJson(route('api.v1.teach.project-assessments.announce', $projectAssessment), [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    #[Test]
    public function gradebook_reopen_is_forbidden_for_an_instructor(): void
    {
        $bundle = $this->gradingBundle('S8H9');

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.reopen', $bundle['offering']))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
