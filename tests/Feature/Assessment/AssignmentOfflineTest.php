<?php

namespace Tests\Feature\Assessment;

use App\Enums\ContentItemType;
use App\Enums\DeliveryMode;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
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

class AssignmentOfflineTest extends TestCase
{
    use RefreshDatabase;

    private function bundle(DeliveryMode $mode = DeliveryMode::Offline): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        [$studentA, $studentB] = User::factory()->withRole(RoleType::Student)->count(2)->create();

        $course = Course::query()->create([
            'code' => 'OFF1',
            'title' => 'Offline Course',
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

        foreach ([$studentA, $studentB] as $student) {
            Enrollment::query()->create([
                'student_id' => $student->id,
                'offering_id' => $offering->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        }

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Assignment,
            'title' => 'Poster',
            'order' => 1,
        ]);

        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions' => 'Bring a printed poster.',
            'allowed_file_types' => ['pdf'],
            'max_points' => 100,
            'delivery_mode' => $mode,
        ]);

        return compact('instructor', 'studentA', 'studentB', 'offering', 'assignment');
    }

    #[Test]
    public function mark_received_creates_the_submission_row_without_any_file_or_text_body(): void
    {
        ['instructor' => $instructor, 'studentA' => $student, 'assignment' => $assignment] = $this->bundle();

        $this->assertSame(0, AssignmentSubmission::query()->count());

        $submission = app(AssignmentService::class)->markReceivedForStudent($instructor, $assignment, $student);

        $this->assertNotNull($submission->id);
        $this->assertNull($submission->text_body);
        $this->assertNull($submission->file_url);
        $this->assertNotNull($submission->received_at);
        $this->assertSame($instructor->id, $submission->received_by_id);
    }

    #[Test]
    public function mark_received_on_an_existing_submission_row_works_too(): void
    {
        ['instructor' => $instructor, 'studentA' => $student, 'assignment' => $assignment] = $this->bundle();

        $submission = AssignmentSubmission::query()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'attempt_no' => 1,
            'submitted_at' => now(),
            'is_late' => false,
        ]);

        $updated = app(AssignmentService::class)->markReceived($instructor, $submission);

        $this->assertNotNull($updated->received_at);
        $this->assertSame($instructor->id, $updated->received_by_id);
    }

    #[Test]
    public function students_cannot_digitally_submit_an_offline_assignment(): void
    {
        ['studentA' => $student, 'assignment' => $assignment] = $this->bundle();

        try {
            app(AssignmentService::class)->submit($student, $assignment, textBody: 'sneaking in');
            $this->fail('Expected offline_use_mark_received validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assignment', $e->errors());
        }

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    #[Test]
    public function mark_received_refuses_an_online_assignment(): void
    {
        ['instructor' => $instructor, 'studentA' => $student, 'assignment' => $assignment] = $this->bundle(DeliveryMode::Online);

        $this->expectException(ValidationException::class);
        app(AssignmentService::class)->markReceivedForStudent($instructor, $assignment, $student);
    }

    #[Test]
    public function bulk_grade_offline_grades_a_batch_of_received_hand_ins(): void
    {
        ['instructor' => $instructor, 'studentA' => $a, 'studentB' => $b, 'assignment' => $assignment] = $this->bundle();

        $service = app(AssignmentService::class);
        $service->markReceivedForStudent($instructor, $assignment, $a);
        $service->markReceivedForStudent($instructor, $assignment, $b);

        $graded = $service->bulkGradeOffline($instructor, $assignment, [
            $a->id => ['raw_score' => 90.0, 'feedback' => 'Great poster'],
            $b->id => ['raw_score' => 75.0, 'feedback' => null],
        ]);

        $this->assertCount(2, $graded);

        $subA = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $a->id)->sole();
        $subB = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('student_id', $b->id)->sole();

        $this->assertEquals(90.0, $subA->final_score);
        $this->assertSame('Great poster', $subA->feedback);
        $this->assertEquals(75.0, $subB->final_score);

        // Offline hand-ins are not marked late by markReceived(), so applyLatePenalty()
        // is a no-op and the raw score survives unpenalized even past a due date.
        $this->assertFalse($subA->is_late);
        $this->assertEquals($subA->raw_score, $subA->final_score);
    }

    #[Test]
    public function bulk_grade_offline_refuses_an_online_assignment(): void
    {
        ['instructor' => $instructor, 'studentA' => $a, 'assignment' => $assignment] = $this->bundle(DeliveryMode::Online);

        $this->expectException(ValidationException::class);
        app(AssignmentService::class)->bulkGradeOffline($instructor, $assignment, [$a->id => ['raw_score' => 80.0]]);
    }
}
