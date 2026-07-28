<?php

namespace Tests\Feature\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\AttemptStatus;
use App\Enums\ComponentKind;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\QuestionType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\StudentProgram;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AssignmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\QuestionBankService;
use App\Services\Gradebook\GradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentEngineTest extends TestCase
{
    use RefreshDatabase;

    private function offeringBundle(User $student): array
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $course = Course::query()->create([
            'code' => 'EXAM1',
            'title' => 'Exam Course',
            'credit_hours' => 3,
            'is_standalone' => false,
            'active' => true,
        ]);

        $program = Program::query()->create([
            'code' => 'P1',
            'name' => 'Program 1',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 15,
            'max_courses_per_semester' => 5,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);
        $program2 = Program::query()->create([
            'code' => 'P2',
            'name' => 'Program 2',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 15,
            'max_courses_per_semester' => 5,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        ProgramCourse::query()->create(['program_id' => $program->id, 'course_id' => $course->id, 'requirement' => RequirementType::Required]);
        ProgramCourse::query()->create(['program_id' => $program2->id, 'course_id' => $course->id, 'requirement' => RequirementType::Required]);

        $sp1 = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);
        $sp2 = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program2->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'student_program_id' => $sp1->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        return compact('course', 'offering', 'program', 'program2', 'sp1', 'sp2');
    }

    #[Test]
    public function timed_exam_autosave_resume_auto_submit_and_objective_grade(): void
    {
        $ins = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $bundle = $this->offeringBundle($student);

        $bank = app(QuestionBankService::class)->createBank($ins, $bundle['course'], 'Bank');
        $q1 = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::McqSingle->value,
            'prompt' => '2+2?',
            'points' => 10,
            'options' => [
                ['text' => '3', 'is_correct' => false],
                ['text' => '4', 'is_correct' => true],
            ],
        ]);
        $q2 = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::TrueFalse->value,
            'prompt' => 'Sky is blue',
            'points' => 10,
            'options' => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => false],
            ],
        ]);

        $assessment = app(AssessmentService::class)->create($ins, $bundle['offering'], [
            'title' => 'Midterm',
            'mode' => AssessmentMode::Exam->value,
            'time_limit_minutes' => 30,
            'attempts_allowed' => 2,
            'draw_from_bank_id' => $bank->id,
            'questions_to_draw' => 2,
            'max_points' => 20,
            'shuffle_questions' => false,
        ]);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertCount(2, $attempt->question_ids);
        $this->assertTrue($attempt->due_at->greaterThan(now()->addMinutes(25)));

        $correctOpt = $q1->options()->where('is_correct', true)->first();
        $tfCorrect = $q2->options()->where('is_correct', true)->first();

        app(AttemptService::class)->autosave($student, $attempt, [
            $q1->id => ['option_id' => $correctOpt->id],
        ]);

        // Resume returns same in-progress attempt
        $resumed = app(AttemptService::class)->start($student, $assessment);
        $this->assertSame($attempt->id, $resumed->id);
        $this->assertCount(1, $resumed->answers);

        // Expire and auto-submit via command
        $attempt->update(['due_at' => now()->subSecond()]);
        Artisan::call('assessments:auto-submit-expired');

        $attempt->refresh();
        $this->assertContains($attempt->status, [AttemptStatus::AutoSubmitted, AttemptStatus::Graded]);
        $this->assertGreaterThanOrEqual(10, (float) $attempt->total_score);

        // Complete second attempt fully graded
        $attempt2 = app(AttemptService::class)->start($student, $assessment);
        app(AttemptService::class)->autosave($student, $attempt2, [
            $q1->id => ['option_id' => $correctOpt->id],
            $q2->id => ['option_id' => $tfCorrect->id],
        ]);
        $attempt2 = app(AttemptService::class)->submit($student, $attempt2);
        $this->assertSame(AttemptStatus::Graded, $attempt2->status);
        $this->assertEquals(20.0, $attempt2->total_score);
    }

    #[Test]
    public function essay_stays_manual_without_ai_key_and_override_works(): void
    {
        config(['services.gemini.key' => null]);
        $ins = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $bundle = $this->offeringBundle($student);

        $bank = app(QuestionBankService::class)->createBank($ins, $bundle['course'], 'Essays');
        $essay = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::Essay->value,
            'prompt' => 'Explain liturgy',
            'points' => 25,
            'ai_key_points' => 'prayer,icon',
        ]);

        $assessment = app(AssessmentService::class)->create($ins, $bundle['offering'], [
            'title' => 'Essay',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 10,
            'max_points' => 25,
        ]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $essay);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        app(AttemptService::class)->autosave($student, $attempt, [
            $essay->id => ['text' => 'A long essay about prayer'],
        ]);
        $attempt = app(AttemptService::class)->submit($student, $attempt);
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);

        $answer = $attempt->answers()->first();
        $this->assertNull($answer->ai_suggested_score);
        $this->assertNull($answer->final_score);

        app(AttemptService::class)->overrideScore($ins, $answer, 22, 'Good');
        $this->assertSame(AttemptStatus::Graded, $attempt->fresh()->status);
        $this->assertEquals(22.0, $attempt->fresh()->total_score);
    }

    #[Test]
    public function late_penalty_and_gradebook_lock_posts_cross_program_records(): void
    {
        $ins = User::factory()->withRole(RoleType::Instructor)->create();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $bundle = $this->offeringBundle($student);

        $component = app(GradebookService::class)->addComponent($ins, $bundle['offering'], [
            'name' => 'Exams',
            'weight_percent' => 100,
            'kind' => ComponentKind::Exam->value,
        ]);

        $bank = app(QuestionBankService::class)->createBank($ins, $bundle['course'], 'B');
        $q = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::McqSingle->value,
            'prompt' => 'Q',
            'points' => 100,
            'options' => [
                ['text' => 'A', 'is_correct' => true],
                ['text' => 'B', 'is_correct' => false],
            ],
        ]);

        $assessment = app(AssessmentService::class)->create($ins, $bundle['offering'], [
            'title' => 'Final',
            'mode' => AssessmentMode::Exam->value,
            'time_limit_minutes' => 15,
            'max_points' => 100,
            'component_id' => $component->id,
            'released' => true,
        ]);
        // create() doesn't accept released in fillable flow as true by default — update
        $assessment->update(['released' => true, 'component_id' => $component->id]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $q);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        $opt = $q->options()->where('is_correct', true)->first();
        app(AttemptService::class)->autosave($student, $attempt, [$q->id => ['option_id' => $opt->id]]);
        app(AttemptService::class)->submit($student, $attempt);

        $week = Week::query()->create([
            'offering_id' => $bundle['offering']->id,
            'number' => 1,
            'title' => 'W1',
            'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => \App\Enums\ContentItemType::Assignment,
            'title' => 'HW',
            'order' => 1,
        ]);
        $assignment = app(AssignmentService::class)->create($ins, $item, [
            'instructions' => 'Do work',
            'due_date' => now()->subDays(2),
            'max_points' => 100,
            'component_id' => $component->id,
        ]);

        $submission = app(AssignmentService::class)->submit($student, $assignment, 'my work');
        $this->assertTrue($submission->is_late);
        $graded = app(AssignmentService::class)->grade($ins, $submission, 100);
        // 2 days late → schedule [0,10,20,30] index min(2,3)=2 → 20%
        $this->assertEquals(80.0, $graded->final_score);

        $this->actingAs($ins)->post(route('admin.gradebook.lock', $bundle['offering']))->assertRedirect();

        $enrollment = Enrollment::query()->where('student_id', $student->id)->first();
        $this->assertSame(GradeStatus::Locked, $enrollment->fresh()->grade_status);
        $this->assertNotNull($enrollment->fresh()->final_letter);

        $record = AcademicRecord::query()->where('enrollment_id', $enrollment->id)->first();
        $this->assertNotNull($record);

        $this->assertSame(2, ProgramRequirementFulfillment::query()->where('academic_record_id', $record->id)->count());
        $this->assertNotNull($bundle['sp1']->fresh()->cached_gpa);
        $this->assertNotNull($bundle['sp2']->fresh()->cached_gpa);

        $this->actingAs($aca)->post(route('admin.gradebook.reopen', $bundle['offering']))->assertRedirect();
        $this->assertSame(GradeStatus::InProgress, $enrollment->fresh()->grade_status);
    }
}
