<?php

namespace Tests\Feature\Assessment;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionVersion;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression guard for the pre-S5 defect described in the gap analysis (G-17's worst
 * part): `submit()` used to `updateOrCreate`, so a second submission destroyed the
 * first row and left a stale grade attached to content the instructor never saw. The
 * fix landed on `main` before this branch started (commit 1b02276,
 * `AssignmentService::submit()` archives into `assignment_submission_versions` and
 * bumps `attempt_no`) — this test asserts that behaviour stays fixed. It is expected
 * to pass immediately; it exists as a durable guard, not to re-discover the bug.
 */
class AssignmentNoSilentOverwriteTest extends TestCase
{
    use RefreshDatabase;

    private function bundle(bool $allowResubmission = true): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'NSO1',
            'title' => 'No Silent Overwrite Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Assignment,
            'title' => 'Essay',
            'order' => 1,
        ]);

        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions' => 'Write an essay.',
            'allowed_file_types' => ['pdf'],
            'max_points' => 100,
            'allow_resubmission' => $allowResubmission,
        ]);

        return compact('instructor', 'student', 'offering', 'assignment');
    }

    #[Test]
    public function a_second_submission_never_mutates_the_first_rows_archived_version(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle();
        $service = app(AssignmentService::class);

        $service->submit($student, $assignment, textBody: 'first', fileUrl: 'uploads/first.pdf');
        $service->submit($student, $assignment, textBody: 'second', fileUrl: 'uploads/second.pdf');
        $service->submit($student, $assignment, textBody: 'third', fileUrl: 'uploads/third.pdf');

        $versions = AssignmentSubmissionVersion::query()->orderBy('attempt_no')->get();
        $this->assertCount(2, $versions);

        // The first archived version must never be touched by later resubmissions.
        $this->assertSame('first', $versions[0]->text_body);
        $this->assertSame('uploads/first.pdf', $versions[0]->file_url);
        $this->assertSame(1, $versions[0]->attempt_no);

        $this->assertSame('second', $versions[1]->text_body);
        $this->assertSame(2, $versions[1]->attempt_no);

        $current = AssignmentSubmission::query()->sole();
        $this->assertSame('third', $current->text_body);
        $this->assertSame(3, $current->attempt_no);
    }

    #[Test]
    public function a_graded_submission_cannot_be_overwritten_when_resubmission_is_disallowed(): void
    {
        ['instructor' => $instructor, 'student' => $student, 'assignment' => $assignment] = $this->bundle(allowResubmission: false);
        $service = app(AssignmentService::class);

        $submission = $service->submit($student, $assignment, textBody: 'only draft');
        $service->grade($instructor, $submission, 95.0, 'Excellent.');

        try {
            $service->submit($student, $assignment, textBody: 'attempted overwrite');
            $this->fail('Expected resubmission_not_allowed validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assignment', $e->errors());
        }

        $submission = $submission->fresh();
        $this->assertSame('only draft', $submission->text_body);
        $this->assertEquals(95.0, $submission->final_score);
        $this->assertSame('Excellent.', $submission->feedback);
        $this->assertSame(0, AssignmentSubmissionVersion::query()->count());
    }

    #[Test]
    public function full_attempt_history_is_retrievable_after_several_resubmissions(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle();
        $service = app(AssignmentService::class);

        $service->submit($student, $assignment, textBody: 'v1');
        $service->submit($student, $assignment, textBody: 'v2');
        $service->submit($student, $assignment, textBody: 'v3');
        $current = $service->submit($student, $assignment, textBody: 'v4');

        $history = $current->versions()->pluck('text_body')->all();
        $this->assertSame(['v1', 'v2', 'v3'], $history);
        $this->assertSame(4, $current->attempt_no);
    }
}
