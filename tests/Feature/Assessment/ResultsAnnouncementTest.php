<?php

namespace Tests\Feature\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\EnrollmentStatus;
use App\Enums\NotificationChannel;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\AssessmentResultAnnouncement;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\User;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResultsAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function announcing_releases_results_and_notifies_each_attempted_student_exactly_once(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        [$a, $b, $c] = User::factory()->withRole(RoleType::Student)->count(3)->create();

        $course = Course::query()->create([
            'code' => 'ANN1',
            'title' => 'Announcement Course',
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

        foreach ([$a, $b, $c] as $student) {
            Enrollment::query()->create([
                'student_id' => $student->id,
                'offering_id' => $offering->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        }

        $assessment = app(AssessmentService::class)->create($instructor, $offering, [
            'title' => 'Final quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 20,
            'max_points' => 10,
        ]);

        // Only A and B attempt; C never does and must never be notified.
        app(AttemptService::class)->start($a, $assessment);
        app(AttemptService::class)->start($b, $assessment);

        $this->assertFalse($assessment->fresh()->released);

        $announcement = app(AssessmentService::class)->announceResults($instructor, $assessment);

        $this->assertTrue($assessment->fresh()->released);
        $this->assertInstanceOf(AssessmentResultAnnouncement::class, $announcement);
        $this->assertNotNull($announcement->announced_at);
        $this->assertSame($instructor->id, $announcement->announced_by_id);

        $this->assertSame(2, Notification::query()->where('type', 'assessments.results_announced')->where('channel', NotificationChannel::InApp)->count());
        $this->assertTrue(Notification::query()->where('user_id', $a->id)->where('type', 'assessments.results_announced')->exists());
        $this->assertTrue(Notification::query()->where('user_id', $b->id)->where('type', 'assessments.results_announced')->exists());
        $this->assertFalse(Notification::query()->where('user_id', $c->id)->where('type', 'assessments.results_announced')->exists());

        $this->assertSame(1, AssessmentResultAnnouncement::query()->where('assessment_id', $assessment->id)->count());
    }

    #[Test]
    public function re_announcing_does_not_duplicate_the_notification(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        [$a, $b] = User::factory()->withRole(RoleType::Student)->count(2)->create();

        $course = Course::query()->create([
            'code' => 'ANN2',
            'title' => 'Re-announce Course',
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

        foreach ([$a, $b] as $student) {
            Enrollment::query()->create([
                'student_id' => $student->id,
                'offering_id' => $offering->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        }

        $assessment = app(AssessmentService::class)->create($instructor, $offering, [
            'title' => 'Re-announce quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 20,
            'max_points' => 10,
        ]);

        app(AttemptService::class)->start($a, $assessment);
        app(AttemptService::class)->start($b, $assessment);

        $first = app(AssessmentService::class)->announceResults($instructor, $assessment);
        $this->assertSame(2, Notification::query()->where('type', 'assessments.results_announced')->where('channel', NotificationChannel::InApp)->count());

        $this->travel(5)->minutes();
        $second = app(AssessmentService::class)->announceResults($instructor, $assessment);

        // Notification count must stay the same — no re-send to already-notified students.
        $this->assertSame(2, Notification::query()->where('type', 'assessments.results_announced')->where('channel', NotificationChannel::InApp)->count());
        // Still exactly one announcement row; announced_at was touched up in place.
        $this->assertSame(1, AssessmentResultAnnouncement::query()->where('assessment_id', $assessment->id)->count());
        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->announced_at->gt($first->announced_at));
    }

    #[Test]
    public function a_student_who_attempts_after_the_first_announcement_is_notified_on_the_next_one(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        [$a, $late] = User::factory()->withRole(RoleType::Student)->count(2)->create();

        $course = Course::query()->create([
            'code' => 'ANN3',
            'title' => 'Late Attempt Course',
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

        foreach ([$a, $late] as $student) {
            Enrollment::query()->create([
                'student_id' => $student->id,
                'offering_id' => $offering->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        }

        $assessment = app(AssessmentService::class)->create($instructor, $offering, [
            'title' => 'Rolling quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 20,
            'attempts_allowed' => 2,
            'max_points' => 10,
        ]);

        app(AttemptService::class)->start($a, $assessment);
        app(AssessmentService::class)->announceResults($instructor, $assessment);
        $this->assertSame(1, Notification::query()->where('type', 'assessments.results_announced')->where('channel', NotificationChannel::InApp)->count());

        app(AttemptService::class)->start($late, $assessment);
        app(AssessmentService::class)->announceResults($instructor, $assessment);

        $this->assertSame(2, Notification::query()->where('type', 'assessments.results_announced')->where('channel', NotificationChannel::InApp)->count());
        $this->assertTrue(Notification::query()->where('user_id', $late->id)->where('type', 'assessments.results_announced')->exists());
    }
}
