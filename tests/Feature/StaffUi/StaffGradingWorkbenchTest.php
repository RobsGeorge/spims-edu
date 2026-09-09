<?php

namespace Tests\Feature\StaffUi;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssignmentService;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gates 13.2 (single-submission view + grading) and 13.3 (AI suggest).
 */
class StaffGradingWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────
    // Fixture helpers
    // ──────────────────────────────────────────────────────────────────────

    private function makeBundle(string $code): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student    = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code'          => $code,
            'title'         => 'Workbench Test',
            'credit_hours'  => 3,
            'is_standalone' => true,
            'active'        => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode'      => OfferingMode::SelfPaced,
            'status'    => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);
        Enrollment::query()->create([
            'student_id'  => $student->id,
            'offering_id' => $offering->id,
            'status'      => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number'      => 1,
            'title'       => 'Week 1',
            'order'       => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type'    => ContentItemType::Assignment,
            'title'   => 'Essay',
            'order'   => 1,
        ]);
        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions'    => 'Write an essay.',
            'allowed_file_types' => ['pdf'],
            'max_points'      => 100,
        ]);
        $service    = app(AssignmentService::class);
        $submission = $service->submit($student, $assignment, textBody: 'This is my essay.');

        return compact('instructor', 'student', 'offering', 'assignment', 'submission');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Gate 13.2
    // ──────────────────────────────────────────────────────────────────────

    #[Test]
    public function grading_writes_through_assignment_service_and_is_audited(): void
    {
        $bundle = $this->makeBundle('WB13A');
        $auditCountBefore = AuditLog::query()->where('action', 'assignments.grade')->count();

        $this->actingAs($bundle['instructor'])
            ->post(route('teach.assignments.submissions.grade', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]), [
                'raw_score' => 88,
                'feedback'  => 'Well done.',
            ])
            ->assertRedirect();

        // Verify graded in DB via service (raw_score persisted, final_score computed)
        $fresh = AssignmentSubmission::query()->findOrFail($bundle['submission']->id);
        $this->assertEquals(88.0, (float) $fresh->raw_score);
        $this->assertNotNull($fresh->graded_at);
        $this->assertSame($bundle['instructor']->id, $fresh->graded_by_id);

        // AuditLog entry must exist (proves service was used, not direct DB write)
        $this->assertGreaterThan(
            $auditCountBefore,
            AuditLog::query()->where('action', 'assignments.grade')->count(),
            'Expected an AuditLog entry for assignments.grade',
        );
    }

    #[Test]
    public function submission_view_shows_all_versions(): void
    {
        $bundle  = $this->makeBundle('WB13B');
        $service = app(AssignmentService::class);

        // First version is already submitted. Now allow and do a resubmission.
        $bundle['assignment']->update(['allow_resubmission' => true]);
        $service->submit($bundle['student'], $bundle['assignment'], textBody: 'Revised essay v2.');

        $fresh = AssignmentSubmission::query()
            ->where('assignment_id', $bundle['assignment']->id)
            ->where('student_id', $bundle['student']->id)
            ->first();

        // There should now be 1 archived version + 1 current (attempt_no = 2)
        $this->assertSame(2, (int) $fresh->attempt_no);
        $this->assertSame(1, $fresh->versions()->count(), 'Expected one archived version');

        $response = $this->actingAs($bundle['instructor'])
            ->get(route('teach.assignments.submissions.show', [
                $bundle['offering'],
                $bundle['assignment'],
                $fresh,
            ]))
            ->assertOk();

        // Both version 1 (archived) and version 2 (latest) must appear
        $response->assertSee('Version 1', false);
        $response->assertSee('Version 2', false);
    }

    #[Test]
    public function submission_view_uses_mobile_first_column_classes(): void
    {
        $bundle = $this->makeBundle('WB13C');

        $response = $this->actingAs($bundle['instructor'])
            ->get(route('teach.assignments.submissions.show', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]))
            ->assertOk();

        $html = $response->getContent();

        // The two-column layout must use mobile-first classes (col-12 col-md-*)
        // so it renders as single-column at 360px
        $this->assertStringContainsString('col-12 col-md-7', $html);
        $this->assertStringContainsString('col-12 col-md-5', $html);
    }

    #[Test]
    public function unauthorized_student_gets_403_on_submission_view(): void
    {
        $bundle = $this->makeBundle('WB13D');

        $this->actingAs($bundle['student'])
            ->get(route('teach.assignments.submissions.show', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]))
            ->assertForbidden();
    }

    #[Test]
    public function unauthorized_student_gets_403_on_grade_post(): void
    {
        $bundle = $this->makeBundle('WB13E');

        $this->actingAs($bundle['student'])
            ->post(route('teach.assignments.submissions.grade', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]), ['raw_score' => 50])
            ->assertForbidden();

        // DB must be unchanged
        $fresh = AssignmentSubmission::query()->findOrFail($bundle['submission']->id);
        $this->assertNull($fresh->final_score);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Gate 13.3 — AI Suggest
    // ──────────────────────────────────────────────────────────────────────

    #[Test]
    public function ai_suggest_does_not_write_any_data(): void
    {
        $bundle = $this->makeBundle('WB13F');

        // Mock the AiClient to return a deterministic suggestion
        $this->instance(AiClient::class, new class implements AiClient {
            public function translate(string $text, string $source, string $target): ?string { return null; }
            public function suggestEssayScore(string $prompt): ?array
            {
                return ['score' => 75.0, 'rationale' => 'Good attempt.'];
            }
        });

        $auditCountBefore = AuditLog::query()->count();
        $scoreBefore      = $bundle['submission']->raw_score;

        $this->actingAs($bundle['instructor'])
            ->postJson(route('teach.assignments.submissions.ai-suggest', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]))
            ->assertOk()
            ->assertJsonStructure(['score', 'feedback']);

        // No AuditLog entry must have been added
        $this->assertSame(
            $auditCountBefore,
            AuditLog::query()->count(),
            'AI suggest must not create AuditLog entries',
        );

        // Submission score must be unchanged
        $fresh = AssignmentSubmission::query()->findOrFail($bundle['submission']->id);
        $this->assertEquals($scoreBefore, $fresh->raw_score);
    }

    #[Test]
    public function ai_suggestion_response_contains_score_and_feedback(): void
    {
        $bundle = $this->makeBundle('WB13G');

        $this->instance(AiClient::class, new class implements AiClient {
            public function translate(string $text, string $source, string $target): ?string { return null; }
            public function suggestEssayScore(string $prompt): ?array
            {
                return ['score' => 60.0, 'rationale' => 'Decent essay.'];
            }
        });

        $response = $this->actingAs($bundle['instructor'])
            ->postJson(route('teach.assignments.submissions.ai-suggest', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]))
            ->assertOk();

        $this->assertEquals(60.0, $response->json('score'));
        $this->assertSame('Decent essay.', $response->json('feedback'));
    }

    #[Test]
    public function ai_suggestion_label_is_present_in_submission_view_html(): void
    {
        $bundle = $this->makeBundle('WB13H');

        $response = $this->actingAs($bundle['instructor'])
            ->get(route('teach.assignments.submissions.show', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]))
            ->assertOk();

        // The AI origin label must be present in the rendered HTML
        $response->assertSee(__('grading.ai_suggestion_label'), false);
    }

    #[Test]
    public function instructor_can_save_different_grade_after_ai_suggestion(): void
    {
        $bundle = $this->makeBundle('WB13I');

        $this->instance(AiClient::class, new class implements AiClient {
            public function translate(string $text, string $source, string $target): ?string { return null; }
            public function suggestEssayScore(string $prompt): ?array
            {
                return ['score' => 50.0, 'rationale' => 'Average.'];
            }
        });

        // Call AI suggest (no write)
        $this->actingAs($bundle['instructor'])
            ->postJson(route('teach.assignments.submissions.ai-suggest', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]))
            ->assertOk();

        // Now submit a completely different grade
        $this->actingAs($bundle['instructor'])
            ->post(route('teach.assignments.submissions.grade', [
                $bundle['offering'],
                $bundle['assignment'],
                $bundle['submission'],
            ]), [
                'raw_score' => 92,
                'feedback'  => 'Actually excellent.',
            ])
            ->assertRedirect();

        $fresh = AssignmentSubmission::query()->findOrFail($bundle['submission']->id);
        $this->assertEquals(92.0, (float) $fresh->raw_score);
        $this->assertSame('Actually excellent.', $fresh->feedback);
    }
}
