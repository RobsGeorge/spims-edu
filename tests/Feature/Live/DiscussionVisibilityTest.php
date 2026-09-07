<?php

namespace Tests\Feature\Live;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\ThreadVisibility;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Discussions\DiscussionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscussionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{instructor: User, author: User, peer: User, offering: CourseOffering}
     */
    private function enrolledBoard(OfferingMode $mode = OfferingMode::Cohort): array
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $author = User::factory()->withRole(RoleType::Student)->create();
        $peer = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'VIS1',
            'title' => 'Visibility Course',
            'credit_hours' => 2,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => $mode,
            'status' => 'OPEN',
            'attendance_threshold_percent' => 60,
        ]);

        $this->staffOffering($instructor, $offering);

        foreach ([$author, $peer] as $student) {
            Enrollment::query()->create([
                'student_id' => $student->id,
                'offering_id' => $offering->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        }

        return compact('instructor', 'author', 'peer', 'offering');
    }

    #[Test]
    public function student_cannot_get_another_students_private_thread(): void
    {
        ['instructor' => $instructor, 'author' => $author, 'peer' => $peer, 'offering' => $offering] = $this->enrolledBoard();

        $board = app(DiscussionService::class)->provisionBoard($instructor, $offering);
        $thread = app(DiscussionService::class)->createThread($author, $board, [
            'title' => 'Private question',
            'body' => 'Only staff should see this',
            'visibility' => ThreadVisibility::PrivateToInstructor->value,
        ]);

        $this->actingAs($peer)
            ->get(route('discussions.thread', $thread))
            ->assertForbidden();
    }

    #[Test]
    public function other_student_cannot_see_private_thread_on_the_board(): void
    {
        ['instructor' => $instructor, 'author' => $author, 'peer' => $peer, 'offering' => $offering] = $this->enrolledBoard();

        $board = app(DiscussionService::class)->provisionBoard($instructor, $offering);
        app(DiscussionService::class)->createThread($author, $board, [
            'title' => 'Hidden private thread',
            'body' => 'Secret',
            'visibility' => ThreadVisibility::PrivateToInstructor->value,
        ]);
        app(DiscussionService::class)->createThread($author, $board, [
            'title' => 'Open classroom thread',
            'body' => 'Everyone',
            'visibility' => ThreadVisibility::Open->value,
        ]);

        $this->actingAs($peer)
            ->get(route('discussions.board', $offering))
            ->assertOk()
            ->assertSee('Open classroom thread')
            ->assertDontSee('Hidden private thread');

        $this->actingAs($author)
            ->get(route('discussions.board', $offering))
            ->assertOk()
            ->assertSee('Hidden private thread');
    }

    #[Test]
    public function author_or_staffed_instructor_can_open_private_thread(): void
    {
        ['instructor' => $instructor, 'author' => $author, 'offering' => $offering] = $this->enrolledBoard();

        $board = app(DiscussionService::class)->provisionBoard($instructor, $offering);
        $thread = app(DiscussionService::class)->createThread($author, $board, [
            'title' => 'Ask the instructor',
            'body' => 'Please help',
            'visibility' => ThreadVisibility::PrivateToInstructor->value,
        ]);

        $this->actingAs($author)
            ->get(route('discussions.thread', $thread))
            ->assertOk()
            ->assertSee('Ask the instructor')
            ->assertSee('Please help');

        $this->actingAs($instructor)
            ->get(route('discussions.thread', $thread))
            ->assertOk()
            ->assertSee('Ask the instructor');

        $this->actingAs($instructor)
            ->get(route('discussions.board', $offering))
            ->assertOk()
            ->assertSee('Ask the instructor');
    }

    #[Test]
    public function provision_board_sets_allow_student_threads_from_offering_mode(): void
    {
        // LearnController::deepLink and storeThread call provisionBoard.
        // ensureBoard only returns an existing row — visiting the board does not create one.
        // Self-paced offerings default allow_student_threads=false; cohort (live) defaults true.
        $actor = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $selfPaced = CourseOffering::query()->create([
            'course_id' => Course::query()->create([
                'code' => 'SELF1',
                'title' => 'Self paced',
                'credit_hours' => 1,
                'is_standalone' => true,
                'active' => true,
            ])->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
            'attendance_threshold_percent' => 60,
        ]);

        $cohort = CourseOffering::query()->create([
            'course_id' => Course::query()->create([
                'code' => 'COH1',
                'title' => 'Cohort live',
                'credit_hours' => 1,
                'is_standalone' => true,
                'active' => true,
            ])->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
            'attendance_threshold_percent' => 60,
        ]);

        $discussions = app(DiscussionService::class);

        $this->assertNull($discussions->ensureBoard($selfPaced));
        $this->assertFalse($discussions->provisionBoard($actor, $selfPaced)->allow_student_threads);
        $this->assertTrue($discussions->provisionBoard($actor, $cohort)->allow_student_threads);
    }
}
