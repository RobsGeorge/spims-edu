<?php

namespace Tests\Feature\Feedback;

use App\Enums\EnrollmentStatus;
use App\Enums\FeedbackQuestionKind;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSurvey;
use App\Models\User;
use App\Services\Feedback\FeedbackSurveyService;
use App\Support\AuthorizeService;

trait FeedbackFixtures
{
    protected function forgetAuthz(): void
    {
        app(AuthorizeService::class)->forgetMatrixCache();
    }

    protected function offering(string $code): CourseOffering
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
            'mode' => OfferingMode::SelfPaced,
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
            'progress_percent' => 0,
        ]);
    }

    /**
     * @return array{instructor: User, admin: User, student: User, offering: CourseOffering, enrollment: Enrollment}
     */
    protected function offeringActors(string $code = 'FB1'): array
    {
        $this->forgetAuthz();

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offering($code);
        $this->staffOffering($instructor, $offering);
        $enrollment = $this->enroll($student, $offering);

        return compact('instructor', 'admin', 'student', 'offering', 'enrollment');
    }

    /**
     * @param  array{prompt?: string, kind?: string, options?: ?array, required?: bool}  $question
     */
    protected function draftSurvey(User $actor, ?CourseOffering $offering, string $title = 'Course eval', array $question = []): FeedbackSurvey
    {
        $service = app(FeedbackSurveyService::class);
        $survey = $service->create($actor, ['title' => $title], $offering);
        $service->addQuestion($actor, $survey, [
            'prompt' => $question['prompt'] ?? 'How was the course?',
            'kind' => $question['kind'] ?? FeedbackQuestionKind::Text->value,
            'options' => $question['options'] ?? null,
            'required' => $question['required'] ?? true,
        ]);

        return $survey->fresh('questions');
    }

    protected function publishedSurvey(User $actor, ?CourseOffering $offering, string $title = 'Course eval', array $question = []): FeedbackSurvey
    {
        $survey = $this->draftSurvey($actor, $offering, $title, $question);

        return app(FeedbackSurveyService::class)->publish($actor, $survey);
    }

    protected function firstQuestion(FeedbackSurvey $survey): FeedbackQuestion
    {
        return $survey->questions()->orderBy('position')->firstOrFail();
    }
}
