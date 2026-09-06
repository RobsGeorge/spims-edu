<?php

namespace Tests\Feature\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\DiscussionGrade;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Discussions\DiscussionService;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorOpsDiscussionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
    }

    #[Test]
    public function instructor_lists_threads_and_can_lock_one(): void
    {
        [$offering, $instructor, $student] = $this->offeringBundle('OD1');
        $discussions = app(DiscussionService::class);
        $board = $discussions->provisionBoard($instructor, $offering);
        $thread = $discussions->createThread($instructor, $board, [
            'title' => 'Week 1',
            'body' => 'Start here',
        ]);

        $this->asInstructor($instructor)
            ->getJson(route('api.v1.teach.offerings.discussions.threads', $offering))
            ->assertOk()
            ->assertJsonPath('data.0.id', $thread->id)
            ->assertJsonPath('data.0.locked', false);

        $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.discussions.threads.moderate', $thread), [
                'locked' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.locked', true);

        $this->assertTrue($thread->fresh()->locked);
    }

    #[Test]
    public function instructor_overrides_a_discussion_grade(): void
    {
        [$offering, $instructor, $student] = $this->offeringBundle('OD2');
        $discussions = app(DiscussionService::class);
        $board = $discussions->provisionBoard($instructor, $offering);
        $thread = $discussions->createThread($instructor, $board, [
            'title' => 'Graded',
            'is_graded' => true,
            'participation_min_words' => 5,
        ]);

        $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.discussions.threads.grade', $thread), [
                'student_id' => $student->id,
                'score' => 85,
                'feedback' => 'Nice',
            ])
            ->assertOk()
            ->assertJsonPath('data.final_score', 85)
            ->assertJsonPath('data.overridden', true)
            ->assertJsonPath('data.student_id', $student->id);

        $this->assertEquals(85.0, DiscussionGrade::query()->where('student_id', $student->id)->value('final_score'));
    }

    #[Test]
    public function instructor_from_another_offering_is_denied(): void
    {
        [$mine, $instructorA] = $this->offeringBundle('OD3A');
        [, $instructorB] = $this->offeringBundle('OD3B');
        $discussions = app(DiscussionService::class);
        $board = $discussions->provisionBoard($instructorA, $mine);
        $thread = $discussions->createThread($instructorA, $board, ['title' => 'A only']);

        $this->asInstructor($instructorB)
            ->getJson(route('api.v1.teach.offerings.discussions.threads', $mine))
            ->assertForbidden();

        $this->asInstructor($instructorB)
            ->postJson(route('api.v1.teach.discussions.threads.moderate', $thread), [
                'locked' => true,
            ])
            ->assertForbidden();

        $this->asInstructor($instructorB)
            ->postJson(route('api.v1.teach.discussions.threads.grade', $thread), [
                'student_id' => $instructorA->id,
                'score' => 10,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: CourseOffering, 1: User, 2: User}
     */
    private function offeringBundle(string $code): array
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => "Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        return [$offering, $instructor, $student];
    }

    private function asInstructor(User $user)
    {
        Auth::forgetGuards();

        return $this->withToken($user->createToken('api', ['role:INSTRUCTOR'])->plainTextToken);
    }
}
