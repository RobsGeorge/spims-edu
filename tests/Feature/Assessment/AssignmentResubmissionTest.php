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
 * The resubmission-overwrite defect itself is already fixed (commit 1b02276); this
 * file covers the remaining S5 work on top of it: the resubmission_deadline window.
 */
class AssignmentResubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function bundle(?\DateTimeInterface $resubmissionDeadline = null): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'RSD1',
            'title' => 'Resubmission Deadline Course',
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
            'allow_resubmission' => true,
            'resubmission_deadline' => $resubmissionDeadline,
        ]);

        return compact('instructor', 'student', 'offering', 'assignment');
    }

    #[Test]
    public function resubmission_is_allowed_inside_the_deadline_window(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle(now()->addDay());

        app(AssignmentService::class)->submit($student, $assignment, textBody: 'first');
        $submission = app(AssignmentService::class)->submit($student, $assignment, textBody: 'second');

        $this->assertSame('second', $submission->text_body);
        $this->assertSame(2, $submission->attempt_no);
    }

    #[Test]
    public function resubmission_is_refused_once_the_deadline_has_passed(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle(now()->addMinute());

        app(AssignmentService::class)->submit($student, $assignment, textBody: 'first');

        $this->travel(2)->minutes();

        try {
            app(AssignmentService::class)->submit($student, $assignment, textBody: 'too late');
            $this->fail('Expected resubmission_deadline_passed validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assignment', $e->errors());
        }

        $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->sole();
        $this->assertSame('first', $submission->text_body);
        $this->assertSame(1, $submission->attempt_no);
    }

    #[Test]
    public function a_first_submission_is_never_blocked_even_if_the_deadline_already_passed(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle(now()->subDay());

        $submission = app(AssignmentService::class)->submit($student, $assignment, textBody: 'only draft');

        $this->assertSame(1, $submission->attempt_no);
        $this->assertSame('only draft', $submission->text_body);
    }

    #[Test]
    public function full_history_is_retrievable_after_multiple_resubmissions_with_late_penalty_applied(): void
    {
        ['instructor' => $instructor, 'student' => $student, 'assignment' => $assignment] = $this->bundle(now()->addDays(10));
        $assignment->update(['due_date' => now()->subDays(3)]);

        $service = app(AssignmentService::class);
        $service->submit($student, $assignment, textBody: 'v1');
        $service->submit($student, $assignment, textBody: 'v2');
        $submission = $service->submit($student, $assignment, textBody: 'v3');

        $this->assertSame(3, $submission->attempt_no);
        $this->assertTrue($submission->is_late);

        $versions = AssignmentSubmissionVersion::query()
            ->where('submission_id', $submission->id)
            ->orderBy('attempt_no')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertSame('v1', $versions[0]->text_body);
        $this->assertSame('v2', $versions[1]->text_body);

        $graded = $service->grade($instructor, $submission, 100.0);
        // 3 days late, default schedule [0,10,20,30] → index min(3,3)=3 → 30%.
        $this->assertEquals(70.0, $graded->final_score);
    }
}
