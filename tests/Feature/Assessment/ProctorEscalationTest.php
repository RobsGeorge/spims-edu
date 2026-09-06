<?php

namespace Tests\Feature\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\ProctorEvent;
use App\Models\User;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\ProctorService;
use App\Services\Assessment\QuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProctorEscalationTest extends TestCase
{
    use RefreshDatabase;

    private function bundle(): array
    {
        config(['assessment.proctor_termination_threshold' => 3]);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'PROC1',
            'title' => 'Proctoring Course',
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

        $bank = app(QuestionBankService::class)->createBank($instructor, $course, 'Proctor bank');
        $essay = app(QuestionBankService::class)->addQuestion($instructor, $bank, [
            'type' => QuestionType::Essay->value,
            'prompt' => 'Explain the exam rules.',
            'points' => 25,
        ]);

        $assessment = app(AssessmentService::class)->create($instructor, $offering, [
            'title' => 'Proctored exam',
            'mode' => AssessmentMode::Exam->value,
            'time_limit_minutes' => 60,
            'attempts_allowed' => 1,
            'max_points' => 25,
        ]);
        app(AssessmentService::class)->attachQuestion($instructor, $assessment, $essay);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        app(AttemptService::class)->autosave($student, $attempt, [$essay->id => ['text' => 'draft answer']]);

        return compact('instructor', 'admin', 'student', 'assessment', 'attempt', 'essay');
    }

    #[Test]
    public function crossing_the_threshold_terminates_the_attempt_and_records_every_event(): void
    {
        ['student' => $student, 'attempt' => $attempt] = $this->bundle();

        app(AttemptService::class)->logFocusLoss($student, $attempt);
        $attempt = $attempt->fresh();
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame(1, $attempt->proctor_warnings);
        $this->assertSame(1, $attempt->focus_loss_count);

        app(AttemptService::class)->logFocusLoss($student, $attempt);
        $attempt = $attempt->fresh();
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame(2, $attempt->proctor_warnings);

        // The third event, of a different type, crosses the threshold of 3.
        app(ProctorService::class)->recordEvent($student, $attempt, 'TAB_SWITCH', ['tab' => 'search-engine']);
        $attempt = $attempt->fresh();

        $this->assertSame(AttemptStatus::Terminated, $attempt->status);
        $this->assertTrue($attempt->terminated_for_cheating);
        $this->assertNotNull($attempt->terminated_at);
        $this->assertNull($attempt->terminated_by_id, 'Automatic termination has no human actor.');
        $this->assertSame(3, $attempt->proctor_warnings);

        $events = ProctorEvent::query()->where('attempt_id', $attempt->id)->orderBy('warning_number')->get();
        $this->assertCount(3, $events);
        $this->assertSame(['FOCUS_LOSS', 'FOCUS_LOSS', 'TAB_SWITCH'], $events->pluck('event_type')->all());
        $this->assertSame([1, 2, 3], $events->pluck('warning_number')->all());
    }

    #[Test]
    public function a_terminated_attempt_and_its_answers_are_never_deleted(): void
    {
        ['student' => $student, 'attempt' => $attempt] = $this->bundle();

        for ($i = 0; $i < 3; $i++) {
            app(AttemptService::class)->logFocusLoss($student, $attempt);
        }

        $this->assertNotNull(\App\Models\AssessmentAttempt::query()->find($attempt->id));
        $this->assertSame(1, \App\Models\AttemptAnswer::query()->where('attempt_id', $attempt->id)->count());
    }

    #[Test]
    public function a_terminated_attempt_refuses_further_answers_and_cannot_be_resumed(): void
    {
        ['student' => $student, 'attempt' => $attempt, 'essay' => $essay] = $this->bundle();

        for ($i = 0; $i < 3; $i++) {
            app(AttemptService::class)->logFocusLoss($student, $attempt);
        }
        $attempt = $attempt->fresh();
        $this->assertTrue($attempt->terminated_for_cheating);

        try {
            app(AttemptService::class)->autosave($student, $attempt, [$essay->id => ['text' => 'sneaky edit']]);
            $this->fail('Expected terminated_for_cheating validation error on autosave.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attempt', $e->errors());
        }

        try {
            app(AttemptService::class)->submit($student, $attempt);
            $this->fail('Expected terminated_for_cheating validation error on submit.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attempt', $e->errors());
        }

        $answer = \App\Models\AttemptAnswer::query()->where('attempt_id', $attempt->id)->sole();
        $this->assertSame('draft answer', $answer->response['text']);
    }

    #[Test]
    public function admin_clear_termination_makes_it_gradeable_but_not_resumable(): void
    {
        ['instructor' => $instructor, 'admin' => $admin, 'student' => $student, 'attempt' => $attempt, 'essay' => $essay] = $this->bundle();

        for ($i = 0; $i < 3; $i++) {
            app(AttemptService::class)->logFocusLoss($student, $attempt);
        }
        $attempt = $attempt->fresh();
        $answer = \App\Models\AttemptAnswer::query()->where('attempt_id', $attempt->id)->sole();

        // Grading is refused while the flag is set.
        try {
            app(AttemptService::class)->overrideScore($instructor, $answer, 20.0, 'Looks fine actually');
            $this->fail('Expected terminated_for_cheating validation error on overrideScore.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attempt', $e->errors());
        }

        $cleared = app(ProctorService::class)->clearTermination($admin, $attempt);
        $this->assertFalse($cleared->terminated_for_cheating);
        $this->assertNotNull($cleared->terminated_at, 'The original termination stays on the record.');
        $this->assertNotNull($cleared->termination_cleared_at);
        $this->assertSame($admin->id, $cleared->termination_cleared_by_id);
        // Status is not reverted to IN_PROGRESS — clearing a flag does not reopen an exam window.
        $this->assertSame(AttemptStatus::Terminated, $cleared->status);

        $graded = app(AttemptService::class)->overrideScore($instructor, $answer, 20.0, 'Looks fine actually');
        $this->assertEquals(20.0, $graded->final_score);

        // Still not resumable for further student answering.
        try {
            app(AttemptService::class)->autosave($student, $attempt->fresh(), [$essay->id => ['text' => 'try again']]);
            $this->fail('Expected not_in_progress validation error on autosave after grading.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attempt', $e->errors());
        }
    }

    #[Test]
    public function clear_termination_is_an_academic_admin_only_school_wide_override(): void
    {
        ['instructor' => $instructor, 'student' => $student, 'attempt' => $attempt] = $this->bundle();

        for ($i = 0; $i < 3; $i++) {
            app(AttemptService::class)->logFocusLoss($student, $attempt);
        }

        $this->expectException(\App\Exceptions\AuthorizationException::class);
        app(ProctorService::class)->clearTermination($instructor, $attempt->fresh());
    }
}
