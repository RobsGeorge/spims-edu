<?php

namespace Tests\Feature\StaffUi;

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
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffDiscussionGradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
        $this->seed(ThemeSeeder::class);
    }

    #[Test]
    public function instructor_grades_enrolled_student_and_score_is_stored(): void
    {
        [$offering, $instructor, $student, $thread] = $this->gradedThread('TDG1');

        $this->actingAs($instructor)
            ->get(route('teach.discussions.index', $offering))
            ->assertOk()
            ->assertSee($thread->title)
            ->assertSee(__('discussions.grade_heading'))
            ->assertSee($student->email)
            ->assertSee('name="student_id"', false)
            ->assertSee('name="score"', false)
            ->assertSee(route('teach.discussions.grade', [$offering, $thread]), false);

        $this->actingAs($instructor)
            ->from(route('teach.discussions.index', $offering))
            ->post(route('teach.discussions.grade', [$offering, $thread]), [
                'student_id' => $student->id,
                'score' => 88.5,
                'feedback' => 'Clear argument',
            ])
            ->assertRedirect(route('teach.discussions.index', $offering));

        $grade = DiscussionGrade::query()
            ->where('thread_id', $thread->id)
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($grade);
        $this->assertEquals(88.5, $grade->final_score);
        $this->assertTrue($grade->overridden);
        $this->assertSame('Clear argument', $grade->feedback);
        $this->assertSame($instructor->id, $grade->graded_by_id);
    }

    #[Test]
    public function staff_sees_grade_form_on_thread_page(): void
    {
        [$offering, $instructor, $student, $thread] = $this->gradedThread('TDG2');

        $this->actingAs($instructor)
            ->get(route('discussions.thread', $thread))
            ->assertOk()
            ->assertSee(__('discussions.grade_heading'))
            ->assertSee($student->email)
            ->assertSee(route('teach.discussions.grade', [$offering, $thread]), false);

        $this->actingAs($instructor)
            ->from(route('discussions.thread', $thread))
            ->post(route('teach.discussions.grade', [$offering, $thread]), [
                'student_id' => $student->id,
                'score' => 70,
                'feedback' => 'Needs more replies',
            ])
            ->assertRedirect(route('discussions.thread', $thread));

        $this->assertEquals(70.0, DiscussionGrade::query()->where('student_id', $student->id)->value('final_score'));
    }

    #[Test]
    public function instructor_from_another_offering_is_forbidden(): void
    {
        [$offering, , $student, $thread] = $this->gradedThread('TDG3A');
        [, $otherInstructor] = $this->offeringBundle('TDG3B');

        $this->actingAs($otherInstructor)
            ->get(route('teach.discussions.index', $offering))
            ->assertForbidden();

        $this->actingAs($otherInstructor)
            ->post(route('teach.discussions.grade', [$offering, $thread]), [
                'student_id' => $student->id,
                'score' => 10,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('discussion_grades', [
            'thread_id' => $thread->id,
            'student_id' => $student->id,
        ]);
    }

    #[Test]
    public function student_cannot_post_a_discussion_grade(): void
    {
        [$offering, , $student, $thread] = $this->gradedThread('TDG4');

        $this->actingAs($student)
            ->get(route('teach.discussions.index', $offering))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('discussions.thread', $thread))
            ->assertOk()
            ->assertDontSee(__('discussions.grade_heading'))
            ->assertDontSee('name="score"', false);

        $this->actingAs($student)
            ->post(route('teach.discussions.grade', [$offering, $thread]), [
                'student_id' => $student->id,
                'score' => 100,
                'feedback' => 'self',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('discussion_grades', [
            'thread_id' => $thread->id,
            'student_id' => $student->id,
            'overridden' => true,
        ]);
    }

    #[Test]
    public function workspace_discussions_tab_enters_teach_grade_board(): void
    {
        [$offering, $instructor] = $this->offeringBundle('TDG5');

        $this->actingAs($instructor)
            ->get(route('teach.show', $offering))
            ->assertOk()
            ->assertSee(route('teach.discussions.index', $offering), false);
    }

    /**
     * @return array{0: CourseOffering, 1: User, 2: User, 3: \App\Models\DiscussionThread}
     */
    private function gradedThread(string $code): array
    {
        [$offering, $instructor, $student] = $this->offeringBundle($code);
        $discussions = app(DiscussionService::class);
        $board = $discussions->provisionBoard($instructor, $offering);
        $thread = $discussions->createThread($instructor, $board, [
            'title' => 'Week 1 prompt',
            'body' => 'Start here',
            'is_graded' => true,
            'participation_min_words' => 5,
        ]);

        return [$offering, $instructor, $student, $thread];
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
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Enrolled',
            'last_name' => 'Student',
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        return [$offering, $instructor, $student];
    }
}
