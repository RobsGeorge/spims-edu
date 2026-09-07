<?php

namespace Tests\Feature\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\ComponentKind;
use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\QuestionType;
use App\Enums\ResultsVisibility;
use App\Enums\RoleType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\QuestionBankService;
use App\Services\Gradebook\GradebookService;
use App\Services\Learning\StudentGradesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentGradesVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     instructor: User,
     *     student: User,
     *     offering: CourseOffering,
     *     enrollment: Enrollment,
     *     homework: \App\Models\GradebookComponent
     * }
     */
    private function worldWithHomework(): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'VIS1',
            'title' => 'Visibility Course',
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

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $homework = app(GradebookService::class)->addComponent($instructor, $offering, [
            'name' => 'Homework',
            'weight_percent' => 60,
            'kind' => ComponentKind::Assignment->value,
        ]);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'W1',
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
            'component_id' => $homework->id,
            'instructions' => 'Write',
            'allowed_file_types' => ['pdf'],
            'max_points' => 100,
        ]);
        AssignmentSubmission::query()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'text_body' => 'done',
            'submitted_at' => now(),
            'final_score' => 100,
            'attempt_no' => 1,
        ]);

        return compact('instructor', 'student', 'offering', 'enrollment', 'homework');
    }

    #[Test]
    public function unannounced_exam_does_not_change_student_running_percent(): void
    {
        $world = $this->worldWithHomework();
        $ins = $world['instructor'];
        $student = $world['student'];
        $offering = $world['offering'];
        $enrollment = $world['enrollment'];

        $before = app(StudentGradesService::class)->forStudent($student)[0]['running_percent'];
        $this->assertEquals(100.0, $before);

        $examComponent = app(GradebookService::class)->addComponent($ins, $offering, [
            'name' => 'Final exam',
            'weight_percent' => 40,
            'kind' => ComponentKind::Exam->value,
        ]);

        $bank = app(QuestionBankService::class)->createBank($ins, $offering->course, 'Hide');
        $q = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::TrueFalse->value,
            'prompt' => 'Sky?',
            'points' => 50,
            'options' => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => false],
            ],
        ]);
        $assessment = app(AssessmentService::class)->create($ins, $offering, [
            'title' => 'Hidden exam',
            'mode' => AssessmentMode::Exam->value,
            'time_limit_minutes' => 10,
            'max_points' => 50,
            'component_id' => $examComponent->id,
            'results_visibility' => ResultsVisibility::OnRelease->value,
            'reveal_answers' => false,
            'shuffle_questions' => false,
        ]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $q);
        app(AssessmentService::class)->release($ins, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        $wrong = $q->options()->where('is_correct', false)->first();
        app(AttemptService::class)->autosave($student, $attempt, [$q->id => ['option_id' => $wrong->id]]);
        app(AttemptService::class)->submit($student, $attempt);
        $this->assertEquals(0.0, $attempt->fresh()->total_score);

        $staff = app(GradebookService::class)->computeEnrollment($enrollment->fresh());
        $this->assertEquals(60.0, $staff['percent']);

        $hidden = app(StudentGradesService::class)->forStudent($student)[0];
        $this->assertEquals($before, $hidden['running_percent']);
        $this->assertEquals(100.0, $hidden['running_percent']);
        $examItem = collect($hidden['items'])->firstWhere('title', 'Hidden exam');
        $this->assertNotNull($examItem);
        $this->assertNull($examItem['score']);

        $studentCompute = app(GradebookService::class)->computeEnrollmentForStudent($enrollment->fresh(), $student);
        $this->assertEquals(100.0, $studentCompute['percent']);

        app(AssessmentService::class)->announceResults($ins, $assessment->fresh());

        $announced = app(StudentGradesService::class)->forStudent($student)[0];
        $this->assertEquals(60.0, $announced['running_percent']);
        $this->assertEquals(
            60.0,
            app(GradebookService::class)->computeEnrollment($enrollment->fresh())['percent']
        );
    }

    #[Test]
    public function student_running_percent_is_null_when_only_hidden_exam_is_scored(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $ins = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => 'VIS2',
            'title' => 'Exam only',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($ins, $offering);
        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $examComponent = app(GradebookService::class)->addComponent($ins, $offering, [
            'name' => 'Exam',
            'weight_percent' => 100,
            'kind' => ComponentKind::Exam->value,
        ]);
        $bank = app(QuestionBankService::class)->createBank($ins, $course, 'Only');
        $q = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::TrueFalse->value,
            'prompt' => 'Q',
            'points' => 10,
            'options' => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => false],
            ],
        ]);
        $assessment = app(AssessmentService::class)->create($ins, $offering, [
            'title' => 'Midterm',
            'mode' => AssessmentMode::Exam->value,
            'time_limit_minutes' => 10,
            'max_points' => 10,
            'component_id' => $examComponent->id,
            'results_visibility' => ResultsVisibility::OnRelease->value,
            'shuffle_questions' => false,
        ]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $q);
        app(AssessmentService::class)->release($ins, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        $opt = $q->options()->where('is_correct', true)->first();
        app(AttemptService::class)->autosave($student, $attempt, [$q->id => ['option_id' => $opt->id]]);
        app(AttemptService::class)->submit($student, $attempt);

        $this->assertEquals(100.0, app(GradebookService::class)->computeEnrollment($enrollment)['percent']);
        $this->assertNull(app(GradebookService::class)->computeEnrollmentForStudent($enrollment, $student)['percent']);
        $this->assertNull(app(StudentGradesService::class)->forStudent($student)[0]['running_percent']);
    }
}
