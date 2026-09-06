<?php

namespace Tests\Feature\Api;

use App\Enums\AssessmentMode;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttemptAnswer;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AssignmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\QuestionBankService;

trait InstructorGradingFixtures
{
    use StudentApiFixtures;

    protected function staffedInstructor(CourseOffering $offering): User
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return $instructor;
    }

    /**
     * @return array{offering: CourseOffering, instructor: User, student: User, enrollment: Enrollment, assignment: Assignment, submission: AssignmentSubmission}
     */
    protected function gradingBundle(string $code): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $offering = $this->offering($code);
        $instructor = $this->staffedInstructor($offering);
        $student = $this->student();
        $enrollment = $this->enroll($student, $offering);
        $assignment = $this->assignmentOn($offering);
        $submission = app(AssignmentService::class)->submit($student, $assignment, 'draft');

        return compact('offering', 'instructor', 'student', 'enrollment', 'assignment', 'submission');
    }

    /**
     * @return array{assessment: Assessment, answer: AttemptAnswer}
     */
    protected function submittedAttempt(User $instructor, CourseOffering $offering, User $student): array
    {
        $bank = app(QuestionBankService::class)->createBank($instructor, $offering->course, 'Bank');
        $question = app(QuestionBankService::class)->addQuestion($instructor, $bank, [
            'type' => QuestionType::TrueFalse->value,
            'prompt' => 'Sky is blue',
            'points' => 10,
            'options' => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => false],
            ],
        ]);

        $assessment = app(AssessmentService::class)->create($instructor, $offering, [
            'title' => 'Quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 20,
            'max_points' => 10,
        ]);
        app(AssessmentService::class)->attachQuestion($instructor, $assessment, $question);
        app(AssessmentService::class)->release($instructor, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        $correct = $question->options()->where('is_correct', true)->first();
        app(AttemptService::class)->autosave($student, $attempt, [
            $question->id => ['option_id' => $correct->id],
        ]);
        $attempt = app(AttemptService::class)->submit($student, $attempt);

        return [
            'assessment' => $assessment,
            'answer' => $attempt->answers()->first(),
        ];
    }

    protected function lockToken(User $instructor, CourseOffering $offering): string
    {
        $token = $this->asApi($instructor, 'INSTRUCTOR')
            ->getJson(route('api.v1.teach.offerings.gradebook', $offering))
            ->assertOk()
            ->json('data.confirmation.confirmation_token');

        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));

        return $token;
    }

    protected function announceToken(User $instructor, Assessment $assessment): string
    {
        $token = $this->asApi($instructor, 'INSTRUCTOR')
            ->getJson(route('api.v1.teach.assessments.attempts', $assessment))
            ->assertOk()
            ->json('data.confirmation.confirmation_token');

        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));

        return $token;
    }
}
