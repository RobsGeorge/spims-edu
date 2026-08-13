<?php

namespace Tests\Feature\Offerings;

use App\Enums\AssessmentMode;
use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\SubmissionType;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentItemCompletion;
use App\Models\EnrollmentWeekCompletion;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LearningPlayerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{offering: CourseOffering, week1: Week, week2: Week, video: ContentItem, reading: ContentItem, enrollment: Enrollment, student: User}
     */
    private function selfPacedBundle(EnrollmentStatus $status = EnrollmentStatus::Enrolled, string $code = 'LMS1'): array
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => $code,
            'title' => 'Learning Course',
            'credit_hours' => 2,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
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
        ]);
        $reading = ContentItem::query()->create([
            'week_id' => $week1->id,
            'type' => ContentItemType::Reading,
            'title' => 'Syllabus',
            'order' => 2,
            'body' => 'Read me',
            'file_url' => 'https://example.com/syllabus.pdf',
        ]);
        ContentItem::query()->create([
            'week_id' => $week2->id,
            'type' => ContentItemType::Text,
            'title' => 'Week 2 text',
            'order' => 1,
            'body' => 'Later',
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => $status,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        return compact('offering', 'week1', 'week2', 'video', 'reading', 'enrollment', 'student');
    }

    #[Test]
    public function guest_and_non_enrolled_cannot_open_player(): void
    {
        $bundle = $this->selfPacedBundle();

        $this->get(route('learn.offering', $bundle['offering']))->assertRedirect();

        $other = User::factory()->withRole(RoleType::Student)->create();
        $this->actingAs($other)
            ->get(route('learn.offering', $bundle['offering']))
            ->assertForbidden();
    }

    #[Test]
    public function waitlisted_and_dropped_denied_completed_allowed(): void
    {
        $wait = $this->selfPacedBundle(EnrollmentStatus::Waitlisted, 'LMSW');
        $this->actingAs($wait['student'])
            ->get(route('learn.offering', $wait['offering']))
            ->assertForbidden();

        $dropped = $this->selfPacedBundle(EnrollmentStatus::Dropped, 'LMSD');
        $this->actingAs($dropped['student'])
            ->get(route('learn.offering', $dropped['offering']))
            ->assertForbidden();

        $done = $this->selfPacedBundle(EnrollmentStatus::Completed, 'LMSC');
        $this->actingAs($done['student'])
            ->get(route('learn.offering', $done['offering']))
            ->assertOk()
            ->assertSee('Welcome');
    }

    #[Test]
    public function cohort_week_respects_unlock_date(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create(['code' => 'COH1', 'title' => 'Cohort', 'credit_hours' => 1, 'active' => true]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $past = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Open week',
            'order' => 1,
            'unlock_date' => now()->subDay(),
        ]);
        $future = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'Future week',
            'order' => 2,
            'unlock_date' => now()->addWeek(),
        ]);
        ContentItem::query()->create([
            'week_id' => $past->id,
            'type' => ContentItemType::Text,
            'title' => 'Past item',
            'order' => 1,
            'body' => 'ok',
        ]);
        ContentItem::query()->create([
            'week_id' => $future->id,
            'type' => ContentItemType::Text,
            'title' => 'Future item',
            'order' => 1,
            'body' => 'locked',
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('learn.week', [$offering, $past]))
            ->assertOk()
            ->assertSee('Past item');

        $this->actingAs($student)
            ->get(route('learn.week', [$offering, $future]))
            ->assertOk()
            ->assertSee(__('learn.week_locked'));
    }

    #[Test]
    public function self_paced_unlocks_week_two_after_all_week_one_items_complete(): void
    {
        $bundle = $this->selfPacedBundle();
        $student = $bundle['student'];
        $offering = $bundle['offering'];

        $this->actingAs($student)
            ->get(route('learn.week', [$offering, $bundle['week2']]))
            ->assertOk()
            ->assertSee(__('learn.week_locked'));

        $this->actingAs($student)
            ->post(route('learn.item.complete', [$offering, $bundle['video']]))
            ->assertRedirect();

        $this->actingAs($student)
            ->get(route('learn.week', [$offering, $bundle['week2']]))
            ->assertOk()
            ->assertSee(__('learn.week_locked'));

        $this->actingAs($student)
            ->post(route('learn.item.complete', [$offering, $bundle['reading']]))
            ->assertRedirect();

        $this->assertDatabaseHas('enrollment_week_completions', [
            'enrollment_id' => $bundle['enrollment']->id,
            'week_id' => $bundle['week1']->id,
        ]);

        $this->actingAs($student)
            ->get(route('learn.week', [$offering, $bundle['week2']]))
            ->assertOk()
            ->assertSee('Week 2 text')
            ->assertDontSee(__('learn.week_locked'));
    }

    #[Test]
    public function progress_percent_updates_and_complete_is_idempotent(): void
    {
        $bundle = $this->selfPacedBundle();
        $student = $bundle['student'];
        $offering = $bundle['offering'];

        $this->actingAs($student)
            ->post(route('learn.item.complete', [$offering, $bundle['video']]))
            ->assertRedirect();
        $this->actingAs($student)
            ->post(route('learn.item.complete', [$offering, $bundle['video']]))
            ->assertRedirect();

        $this->assertSame(1, EnrollmentItemCompletion::query()->where('enrollment_id', $bundle['enrollment']->id)->count());

        // 1 of 3 items
        $this->assertEquals(33.33, round((float) $bundle['enrollment']->fresh()->progress_percent, 2));

        $this->actingAs($student)
            ->post(route('learn.item.complete', [$offering, $bundle['reading']]))
            ->assertRedirect();

        $this->assertEquals(66.67, round((float) $bundle['enrollment']->fresh()->progress_percent, 2));
    }

    #[Test]
    public function assignment_submit_records_item_completion(): void
    {
        $bundle = $this->selfPacedBundle();
        $assignmentItem = ContentItem::query()->create([
            'week_id' => $bundle['week1']->id,
            'type' => ContentItemType::Assignment,
            'title' => 'HW1',
            'order' => 3,
        ]);
        $assignment = Assignment::query()->create([
            'content_item_id' => $assignmentItem->id,
            'instructions' => 'Do it',
            'submission_type' => SubmissionType::Text,
            'allowed_file_types' => [],
            'max_points' => 10,
            'released' => true,
        ]);

        app(AssignmentService::class)->submit($bundle['student'], $assignment, textBody: 'done');

        $this->assertDatabaseHas('enrollment_item_completions', [
            'enrollment_id' => $bundle['enrollment']->id,
            'content_item_id' => $assignmentItem->id,
        ]);
    }

    #[Test]
    public function deep_links_resolve_for_assignment_and_assessment(): void
    {
        $bundle = $this->selfPacedBundle();
        $offering = $bundle['offering'];
        $student = $bundle['student'];

        $assignmentItem = ContentItem::query()->create([
            'week_id' => $bundle['week1']->id,
            'type' => ContentItemType::Assignment,
            'title' => 'HW link',
            'order' => 3,
        ]);
        $assignment = Assignment::query()->create([
            'content_item_id' => $assignmentItem->id,
            'instructions' => 'Do it',
            'submission_type' => SubmissionType::Text,
            'allowed_file_types' => [],
            'max_points' => 10,
            'released' => true,
        ]);

        $quizItem = ContentItem::query()->create([
            'week_id' => $bundle['week1']->id,
            'type' => ContentItemType::Quiz,
            'title' => 'Quiz link',
            'order' => 4,
        ]);
        $assessment = Assessment::query()->create([
            'offering_id' => $offering->id,
            'content_item_id' => $quizItem->id,
            'mode' => AssessmentMode::Quiz,
            'title' => 'Q1',
            'language' => 'en',
            'attempts_allowed' => 1,
            'released' => true,
        ]);

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $assignmentItem]))
            ->assertRedirect(route('assignments.show', $assignment));

        $this->actingAs($student)
            ->get(route('learn.item', [$offering, $quizItem]))
            ->assertRedirect(route('assessments.show', $assessment));
    }

    #[Test]
    public function enrollments_index_shows_open_course_cta(): void
    {
        $bundle = $this->selfPacedBundle();

        $this->actingAs($bundle['student'])
            ->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee(__('learning.open_player'))
            ->assertSee(route('courses.player', $bundle['offering']), false);
    }
}
