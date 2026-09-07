<?php

namespace Tests\Feature\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\RoleType;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorGradingApiTest extends TestCase
{
    use InstructorGradingFixtures;
    use RefreshDatabase;

    #[Test]
    public function instructor_grades_a_submission_overrides_an_answer_submits_and_locks(): void
    {
        $bundle = $this->gradingBundle('S8G1');
        $attempt = $this->submittedAttempt($bundle['instructor'], $bundle['offering'], $bundle['student']);

        $graded = $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.submissions.grade', $bundle['submission']), [
                'raw_score' => 80,
                'feedback' => 'Solid work',
            ])
            ->assertOk();

        $this->assertEquals(80, $graded->json('data.raw_score'));
        $this->assertEquals(80, $graded->json('data.final_score'));
        $this->assertSame('Solid work', $graded->json('data.feedback'));

        $overridden = $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.answers.grade', $attempt['answer']), [
                'final_score' => 9,
                'feedback' => 'Adjusted',
            ])
            ->assertOk();

        $this->assertEquals(9, $overridden->json('data.final_score'));
        $this->assertSame('Adjusted', $overridden->json('data.feedback'));

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.submit', $bundle['offering']))
            ->assertOk()
            ->assertJsonPath('data.submitted', true);

        $this->assertSame(
            GradeStatus::Submitted,
            Enrollment::query()->find($bundle['enrollment']->id)->grade_status
        );

        $confirmation = $this->lockToken($bundle['instructor'], $bundle['offering']);
        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => $confirmation,
            ])
            ->assertOk()
            ->assertJsonPath('data.locked', true);

        $this->assertSame(
            GradeStatus::Locked,
            Enrollment::query()->find($bundle['enrollment']->id)->grade_status
        );
    }

    #[Test]
    public function locked_gradebook_rejects_further_grading(): void
    {
        $bundle = $this->gradingBundle('S8G2');
        $attempt = $this->submittedAttempt($bundle['instructor'], $bundle['offering'], $bundle['student']);

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.submissions.grade', $bundle['submission']), [
                'raw_score' => 70,
            ])
            ->assertOk();

        $confirmation = $this->lockToken($bundle['instructor'], $bundle['offering']);
        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => $confirmation,
            ])
            ->assertOk();

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.submissions.grade', $bundle['submission']), [
                'raw_score' => 10,
                'feedback' => 'too late',
            ])
            ->assertStatus(423)
            ->assertJsonPath('code', 'LOCKED');

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.answers.grade', $attempt['answer']), [
                'final_score' => 1,
            ])
            ->assertStatus(423)
            ->assertJsonPath('code', 'LOCKED');
    }

    #[Test]
    public function assignment_dashboard_returns_counts(): void
    {
        $bundle = $this->gradingBundle('S8G3');

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.submissions.grade', $bundle['submission']), [
                'raw_score' => 90,
            ])
            ->assertOk();

        $response = $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->getJson(route('api.v1.teach.offerings.assignments', $bundle['offering']))
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('assignment_id', $bundle['assignment']->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['enrolled_count']);
        $this->assertSame(1, $row['submitted_count']);
        $this->assertSame(0, $row['ungraded_count']);
        $this->assertSame(0, $row['overdue_count']);
    }

    #[Test]
    public function academic_admin_can_reopen_a_locked_gradebook_with_confirmation(): void
    {
        $bundle = $this->gradingBundle('S8GR1');
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $confirmation = $this->lockToken($bundle['instructor'], $bundle['offering']);
        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.lock', $bundle['offering']), [
                'confirmation' => $confirmation,
            ])
            ->assertOk();

        $enrollment = Enrollment::query()->find($bundle['enrollment']->id);
        $this->assertSame(GradeStatus::Locked, $enrollment->grade_status);

        $token = $this->asApi($admin, 'ACADEMIC_ADMIN')
            ->getJson(route('api.v1.teach.offerings.gradebook', $bundle['offering']))
            ->assertOk()
            ->json('data.reopen_confirmation.confirmation_token');

        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));

        $this->asApi($admin, 'ACADEMIC_ADMIN')
            ->postJson(route('api.v1.teach.offerings.gradebook.reopen', $bundle['offering']), [
                'confirmation' => $token,
            ])
            ->assertOk()
            ->assertJsonPath('data.reopened', true);

        $reopened = Enrollment::query()->find($bundle['enrollment']->id);
        $this->assertSame(GradeStatus::InProgress, $reopened->grade_status);
        $this->assertSame(EnrollmentStatus::Enrolled, $reopened->status);
    }

    #[Test]
    public function instructor_and_student_cannot_reopen_the_gradebook(): void
    {
        $bundle = $this->gradingBundle('S8GR2');

        $this->asApi($bundle['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.offerings.gradebook.reopen', $bundle['offering']), [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->asApi($bundle['student'], 'STUDENT')
            ->postJson(route('api.v1.teach.offerings.gradebook.reopen', $bundle['offering']), [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
