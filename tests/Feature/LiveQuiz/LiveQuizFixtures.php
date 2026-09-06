<?php

namespace Tests\Feature\LiveQuiz;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\LiveQuiz;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Models\User;
use App\Services\LiveQuiz\LiveQuizHostService;

trait LiveQuizFixtures
{
    protected function offering(string $code = 'LQ1'): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => "Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
    }

    protected function enroll(User $student, CourseOffering $offering): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);
    }

    protected function instructorOn(CourseOffering $offering): User
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return $instructor;
    }

    protected function studentOn(CourseOffering $offering): User
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        return $student;
    }

    /**
     * @param  array{time_limit_seconds?: int, points?: int}  $overrides
     */
    protected function readyQuiz(User $instructor, CourseOffering $offering, array $overrides = []): LiveQuiz
    {
        return app(LiveQuizHostService::class)->createQuiz($instructor, $offering, 'Pop quiz', [
            [
                'prompt' => 'What is 2+2?',
                'time_limit_seconds' => $overrides['time_limit_seconds'] ?? 30,
                'points' => $overrides['points'] ?? 1000,
                'options' => [
                    ['label' => '3', 'is_correct' => false],
                    ['label' => '4', 'is_correct' => true],
                ],
            ],
            [
                'prompt' => 'Capital of Egypt?',
                'time_limit_seconds' => 20,
                'points' => 500,
                'options' => [
                    ['label' => 'Cairo', 'is_correct' => true],
                    ['label' => 'Alexandria', 'is_correct' => false],
                ],
            ],
        ]);
    }

    protected function firstQuestion(LiveQuiz $quiz): LiveQuizQuestion
    {
        return $quiz->questions()->with('options')->orderBy('position')->firstOrFail();
    }

    protected function correctOption(LiveQuizQuestion $question)
    {
        return $question->options->firstWhere('is_correct', true);
    }

    protected function wrongOption(LiveQuizQuestion $question)
    {
        return $question->options->firstWhere('is_correct', false);
    }

    protected function startLobby(User $instructor, LiveQuiz $quiz): LiveQuizSession
    {
        return app(LiveQuizHostService::class)->startSession($instructor, $quiz);
    }
}
