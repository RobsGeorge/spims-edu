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
 * Guards the submission history invariant: a resubmission must never destroy the
 * previous attempt, and must never leave a stale grade attached to new content.
 */
class AssignmentResubmissionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{student: User, assignment: Assignment, offering: CourseOffering} */
    private function bundle(bool $allowResubmission = true): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $student = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'ASG1',
            'title' => 'Assignment Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

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

        return ['student' => $student, 'assignment' => $assignment, 'offering' => $offering];
    }

    #[Test]
    public function resubmission_archives_the_previous_attempt_instead_of_overwriting_it(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle();
        $service = app(AssignmentService::class);

        $service->submit($student, $assignment, textBody: 'first draft', fileUrl: 'uploads/first.pdf');
        $service->submit($student, $assignment, textBody: 'second draft', fileUrl: 'uploads/second.pdf');

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->sole();

        $this->assertSame('second draft', $submission->text_body);
        $this->assertSame('uploads/second.pdf', $submission->file_url);
        $this->assertSame(2, $submission->attempt_no);

        $versions = AssignmentSubmissionVersion::query()
            ->where('submission_id', $submission->id)
            ->get();

        $this->assertCount(1, $versions, 'The first attempt must be retained.');
        $this->assertSame('first draft', $versions->first()->text_body);
        $this->assertSame('uploads/first.pdf', $versions->first()->file_url);
        $this->assertSame(1, $versions->first()->attempt_no);
    }

    #[Test]
    public function resubmission_clears_the_stale_grade_and_preserves_it_on_the_archived_attempt(): void
    {
        ['student' => $student, 'assignment' => $assignment, 'offering' => $offering] = $this->bundle();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);
        $service = app(AssignmentService::class);

        $submission = $service->submit($student, $assignment, textBody: 'first draft');
        $service->grade($instructor, $submission, 88.0, 'Good work.');

        $service->submit($student, $assignment, textBody: 'second draft');

        $submission = $submission->fresh();

        // The grade described the first draft; it must not survive onto the second.
        $this->assertNull($submission->raw_score);
        $this->assertNull($submission->final_score);
        $this->assertNull($submission->feedback);
        $this->assertNull($submission->graded_at);
        $this->assertNull($submission->graded_by_id);

        $archived = AssignmentSubmissionVersion::query()
            ->where('submission_id', $submission->id)
            ->where('attempt_no', 1)
            ->sole();

        $this->assertSame(88.0, $archived->raw_score);
        $this->assertSame('Good work.', $archived->feedback);
        $this->assertSame($instructor->id, $archived->graded_by_id);
        $this->assertNotNull($archived->graded_at);
    }

    #[Test]
    public function resubmission_is_refused_when_the_assignment_disallows_it(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle(allowResubmission: false);
        $service = app(AssignmentService::class);

        $service->submit($student, $assignment, textBody: 'only draft');

        $this->expectException(ValidationException::class);

        try {
            $service->submit($student, $assignment, textBody: 'sneaky replacement');
        } finally {
            $submission = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->sole();

            $this->assertSame('only draft', $submission->text_body);
            $this->assertSame(1, $submission->attempt_no);
            $this->assertSame(0, AssignmentSubmissionVersion::query()->count());
        }
    }

    #[Test]
    public function first_submission_creates_a_single_attempt_with_no_history(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->bundle();

        $submission = app(AssignmentService::class)->submit($student, $assignment, textBody: 'only draft');

        $this->assertSame(1, $submission->attempt_no);
        $this->assertSame(0, AssignmentSubmissionVersion::query()->count());
        $this->assertSame(1, AssignmentSubmission::query()->count());
    }
}
