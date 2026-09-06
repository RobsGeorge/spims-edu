<?php

namespace Tests\Feature\Api;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\SubmissionType;
use App\Models\Assignment;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;

trait StudentApiFixtures
{
    protected function apiToken(User $user, string $role = 'STUDENT'): string
    {
        return $user->createToken('api', ["role:{$role}"])->plainTextToken;
    }

    protected function student(array $attrs = []): User
    {
        return User::factory()->withRole(RoleType::Student)->create($attrs);
    }

    protected function offering(string $code, OfferingMode $mode = OfferingMode::SelfPaced, array $courseAttrs = []): CourseOffering
    {
        $course = Course::query()->create(array_merge([
            'code' => $code,
            'title' => "Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ], $courseAttrs));

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => $mode,
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
     * @return array{offering: CourseOffering, week1: Week, week2: Week, video: ContentItem, reading: ContentItem, locked: ContentItem, enrollment: Enrollment, student: User}
     */
    protected function playerBundle(string $code = 'S6A'): array
    {
        $student = $this->student();
        $offering = $this->offering($code, OfferingMode::SelfPaced);
        $week1 = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Intro',
            'order' => 1,
        ]);
        $week2 = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'Next',
            'order' => 2,
        ]);
        $video = ContentItem::query()->create([
            'week_id' => $week1->id,
            'type' => ContentItemType::Video,
            'title' => 'Welcome',
            'order' => 1,
            'vimeo_id' => '999',
            'body' => 'Video body',
        ]);
        $reading = ContentItem::query()->create([
            'week_id' => $week1->id,
            'type' => ContentItemType::Reading,
            'title' => 'Syllabus',
            'order' => 2,
            'body' => 'Read me',
            'file_url' => 'https://example.com/syllabus.pdf',
        ]);
        $locked = ContentItem::query()->create([
            'week_id' => $week2->id,
            'type' => ContentItemType::Text,
            'title' => 'Week 2 text',
            'order' => 1,
            'body' => 'SECRET BODY',
        ]);
        $enrollment = $this->enroll($student, $offering);

        return compact('offering', 'week1', 'week2', 'video', 'reading', 'locked', 'enrollment', 'student');
    }

    protected function assignmentOn(CourseOffering $offering, bool $released = true, bool $allowResubmission = true, string $title = 'Essay'): Assignment
    {
        $week = Week::query()->firstOrCreate(
            ['offering_id' => $offering->id, 'number' => 1],
            ['title' => 'Week 1', 'order' => 1],
        );
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Assignment,
            'title' => $title,
            'order' => 10,
        ]);

        return Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions' => 'Write an essay.',
            'submission_type' => SubmissionType::Both,
            'allowed_file_types' => ['pdf'],
            'max_points' => 100,
            'released' => $released,
            'allow_resubmission' => $allowResubmission,
        ]);
    }
}
