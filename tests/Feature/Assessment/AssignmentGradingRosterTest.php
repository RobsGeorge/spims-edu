<?php

namespace Tests\Feature\Assessment;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gate 13.1 — Submissions Roster.
 *
 * Verifies pagination, filter composition, "grade next" navigation,
 * and authorization for the grading workbench roster.
 */
class AssignmentGradingRosterTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────
    // Fixture helpers
    // ──────────────────────────────────────────────────────────────────────

    private function makeBundle(string $code): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $course = Course::query()->create([
            'code'          => $code,
            'title'         => 'Roster Test',
            'credit_hours'  => 3,
            'is_standalone' => true,
            'active'        => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode'      => OfferingMode::SelfPaced,
            'status'    => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number'      => 1,
            'title'       => 'Week 1',
            'order'       => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type'    => ContentItemType::Assignment,
            'title'   => 'Essay',
            'order'   => 1,
        ]);
        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions'    => 'Write an essay.',
            'allowed_file_types' => ['pdf'],
            'max_points'      => 100,
        ]);

        return compact('instructor', 'offering', 'assignment', 'course');
    }

    private function enroll(User $student, CourseOffering $offering): void
    {
        Enrollment::query()->firstOrCreate(
            ['student_id' => $student->id, 'offering_id' => $offering->id],
            ['status' => EnrollmentStatus::Enrolled, 'enrolled_at' => now()],
        );
    }

    // ──────────────────────────────────────────────────────────────────────

    #[Test]
    public function roster_paginates_with_thirty_plus_submissions(): void
    {
        $bundle = $this->makeBundle('GR13A');

        // Create 35 enrolled students, each submitting.
        $service = app(AssignmentService::class);
        for ($i = 0; $i < 35; $i++) {
            $student = User::factory()->withRole(RoleType::Student)->create();
            $this->enroll($student, $bundle['offering']);
            $service->submit($student, $bundle['assignment'], textBody: "Essay $i");
        }

        $response = $this->actingAs($bundle['instructor'])
            ->get(route('teach.assignments.submissions.index', [
                $bundle['offering'],
                $bundle['assignment'],
            ]))
            ->assertOk();

        // Pagination must be rendered (Laravel wraps links in <nav role="navigation">)
        $response->assertSee('navigation', false);
    }

    #[Test]
    public function filters_compose_status_and_date_range_simultaneously(): void
    {
        $bundle  = $this->makeBundle('GR13B');
        $service = app(AssignmentService::class);

        $studentA = User::factory()->withRole(RoleType::Student)->create();
        $studentB = User::factory()->withRole(RoleType::Student)->create();
        $studentC = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($studentA, $bundle['offering']);
        $this->enroll($studentB, $bundle['offering']);
        $this->enroll($studentC, $bundle['offering']);

        // A submits today (ungraded)
        $subA = $service->submit($studentA, $bundle['assignment'], textBody: 'A essay');

        // B submits today, then gets graded
        $subB = $service->submit($studentB, $bundle['assignment'], textBody: 'B essay');
        $service->grade($bundle['instructor'], $subB, 80.0);

        // C does not submit

        // Filter: status=submitted (only A), from=today
        $from = now()->format('Y-m-d');
        $response = $this->actingAs($bundle['instructor'])
            ->get(route('teach.assignments.submissions.index', [
                $bundle['offering'],
                $bundle['assignment'],
                'status' => 'submitted',
                'from'   => $from,
            ]))
            ->assertOk();

        // Should show A (submitted, within date range)
        $response->assertSee($studentA->first_name);
        // Should NOT show B (graded, not "submitted" status)
        $response->assertDontSee($studentB->first_name);
        // C has no submission so is filtered by status=submitted
        $response->assertDontSee($studentC->first_name);
    }

    #[Test]
    public function grade_next_ungraded_skips_graded_submissions(): void
    {
        $bundle  = $this->makeBundle('GR13C');
        $service = app(AssignmentService::class);

        $studentA = User::factory()->withRole(RoleType::Student)->create();
        $studentB = User::factory()->withRole(RoleType::Student)->create();
        $studentC = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($studentA, $bundle['offering']);
        $this->enroll($studentB, $bundle['offering']);
        $this->enroll($studentC, $bundle['offering']);

        $subA = $service->submit($studentA, $bundle['assignment'], textBody: 'A');
        $subB = $service->submit($studentB, $bundle['assignment'], textBody: 'B');
        $subC = $service->submit($studentC, $bundle['assignment'], textBody: 'C');

        // Grade A and B, leave C ungraded
        $service->grade($bundle['instructor'], $subA, 90.0);
        $service->grade($bundle['instructor'], $subB, 85.0);

        $this->actingAs($bundle['instructor'])
            ->get(route('teach.assignments.submissions.next-ungraded', [
                $bundle['offering'],
                $bundle['assignment'],
            ]))
            ->assertRedirect(
                route('teach.assignments.submissions.show', [
                    $bundle['offering'],
                    $bundle['assignment'],
                    $subC,
                ])
            );
    }

    #[Test]
    public function grade_next_ungraded_terminates_cleanly_when_all_graded(): void
    {
        $bundle  = $this->makeBundle('GR13D');
        $service = app(AssignmentService::class);

        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $bundle['offering']);

        $sub = $service->submit($student, $bundle['assignment'], textBody: 'essay');
        $service->grade($bundle['instructor'], $sub, 95.0);

        // All graded — should redirect to roster with nothing_to_grade session flag
        $this->actingAs($bundle['instructor'])
            ->get(route('teach.assignments.submissions.next-ungraded', [
                $bundle['offering'],
                $bundle['assignment'],
            ]))
            ->assertRedirect(
                route('teach.assignments.submissions.index', [
                    $bundle['offering'],
                    $bundle['assignment'],
                ])
            );
    }

    #[Test]
    public function unauthorized_user_without_grade_permission_gets_403(): void
    {
        $bundle  = $this->makeBundle('GR13E');

        // A plain student has no assignments.grade permission
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->get(route('teach.assignments.submissions.index', [
                $bundle['offering'],
                $bundle['assignment'],
            ]))
            ->assertForbidden();
    }
}
