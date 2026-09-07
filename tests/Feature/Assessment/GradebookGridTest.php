<?php

namespace Tests\Feature\Assessment;

use App\Enums\ComponentKind;
use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\Gradebook\GradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradebookGridTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{offering: CourseOffering, instructor: User, student: User, enrollment: Enrollment}
     */
    private function staffedGradebook(string $code = 'GB1'): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Nardine',
            'last_name' => 'Youssef',
            'email' => 'nardine.youssef@example.com',
        ]);

        $course = Course::query()->create([
            'code' => $code,
            'title' => "Course $code",
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

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        return compact('offering', 'instructor', 'student', 'enrollment');
    }

    #[Test]
    public function staffed_instructor_sees_student_by_component_grid(): void
    {
        $bundle = $this->staffedGradebook('GRID');
        $instructor = $bundle['instructor'];
        $offering = $bundle['offering'];

        app(GradebookService::class)->addComponent($instructor, $offering, [
            'name' => 'Midterm Essays',
            'weight_percent' => 40,
            'kind' => ComponentKind::Assignment->value,
        ]);
        app(GradebookService::class)->addComponent($instructor, $offering, [
            'name' => 'Participation',
            'weight_percent' => 20,
            'kind' => ComponentKind::Discussion->value,
        ]);

        $this->actingAs($instructor)
            ->get(route('admin.gradebook.show', $offering))
            ->assertOk()
            ->assertSee('gradebook-grid', false)
            ->assertSee('Midterm Essays')
            ->assertSee('Participation')
            ->assertSee('Nardine Youssef')
            ->assertSee('nardine.youssef@example.com')
            ->assertSee(__('assessment.final_percent'))
            ->assertSee(__('assessment.weight_sum', ['sum' => 60]))
            ->assertSee(__('assessment.weights_not_100', ['sum' => 60]))
            ->assertSee(ComponentKind::Attendance->value, false)
            ->assertSee(ComponentKind::Discussion->value, false)
            ->assertSee(__('assessment.kind_ATTENDANCE'))
            ->assertSee(__('assessment.kind_DISCUSSION'))
            ->assertSee(__('assessment.export_csv'))
            ->assertSee(__('teach.enrollment'))
            ->assertSee(EnrollmentStatus::Enrolled->value);
    }

    #[Test]
    public function grid_shows_completed_then_enrolled_after_gradebook_reopen(): void
    {
        $bundle = $this->staffedGradebook('REOP');
        $instructor = $bundle['instructor'];
        $offering = $bundle['offering'];
        $enrollment = $bundle['enrollment'];
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $enrollment->update([
            'final_percent' => 95,
            'final_letter' => 'A',
            'final_gpa_points' => 4,
            'grade_status' => GradeStatus::Submitted,
            'grade_type' => GradeType::Standard,
        ]);

        app(GradebookService::class)->lockGrades($instructor, $offering);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->fresh()->status);

        $this->actingAs($admin)
            ->get(route('admin.gradebook.show', $offering))
            ->assertOk()
            ->assertSee(EnrollmentStatus::Completed->value);

        $this->actingAs($admin)
            ->post(route('admin.gradebook.reopen', $offering))
            ->assertRedirect();

        $this->assertSame(EnrollmentStatus::Enrolled, $enrollment->fresh()->status);
        $this->assertSame(GradeStatus::InProgress, $enrollment->fresh()->grade_status);

        $this->actingAs($admin)
            ->get(route('admin.gradebook.show', $offering))
            ->assertOk()
            ->assertSee(EnrollmentStatus::Enrolled->value)
            ->assertDontSee(EnrollmentStatus::Completed->value);
    }

    #[Test]
    public function csv_lists_the_enrolled_student_and_is_forbidden_for_a_student(): void
    {
        $bundle = $this->staffedGradebook('CSV1');
        $instructor = $bundle['instructor'];
        $offering = $bundle['offering'];
        $student = $bundle['student'];

        app(GradebookService::class)->addComponent($instructor, $offering, [
            'name' => 'Homework',
            'weight_percent' => 100,
            'kind' => ComponentKind::Assignment->value,
        ]);

        $this->actingAs($instructor)
            ->get(route('admin.gradebook.csv', $offering))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('student_name,email')
            ->assertSee('Nardine Youssef')
            ->assertSee('nardine.youssef@example.com')
            ->assertSee('Homework')
            ->assertSee('final_percent,letter');

        $this->assertSame(1, AuditLog::query()->where('action', 'gradebook.export')->where('entity_id', $offering->id)->count());

        $this->actingAs($student)
            ->get(route('admin.gradebook.csv', $offering))
            ->assertForbidden();

        $token = $student->createToken('api', ['role:STUDENT'])->plainTextToken;
        $this->withToken($token)
            ->get(route('admin.gradebook.csv', $offering))
            ->assertForbidden();
    }

    #[Test]
    public function weights_that_do_not_sum_to_100_still_renormalize(): void
    {
        $bundle = $this->staffedGradebook('REN1');
        $instructor = $bundle['instructor'];
        $offering = $bundle['offering'];
        $student = $bundle['student'];
        $enrollment = $bundle['enrollment'];

        $homework = app(GradebookService::class)->addComponent($instructor, $offering, [
            'name' => 'Homework',
            'weight_percent' => 60,
            'kind' => ComponentKind::Assignment->value,
        ]);
        $quiz = app(GradebookService::class)->addComponent($instructor, $offering, [
            'name' => 'Quiz',
            'weight_percent' => 20,
            'kind' => ComponentKind::Assignment->value,
        ]);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'W1',
            'order' => 1,
        ]);
        $this->scoredAssignment($week, $homework->id, $student->id, 100, 100);
        $this->scoredAssignment($week, $quiz->id, $student->id, 50, 100);

        $computed = app(GradebookService::class)->computeEnrollment($enrollment);
        $byName = collect($computed['components'])->keyBy('name');

        // raw weighted = 100*0.60 + 50*0.20 = 70; renormalized over 80 → 87.5
        $this->assertEquals(100.0, $byName['Homework']['score']);
        $this->assertEquals(50.0, $byName['Quiz']['score']);
        $this->assertEquals(80.0, collect($computed['components'])->sum('weight'));
        $this->assertEquals(87.5, $computed['percent']);
        $this->assertNotEquals(70.0, $computed['percent']);
    }

    #[Test]
    public function preloaded_grid_matches_unpreloaded_component_percent(): void
    {
        $bundle = $this->staffedGradebook('PRE1');
        $instructor = $bundle['instructor'];
        $offering = $bundle['offering'];
        $student = $bundle['student'];
        $enrollment = $bundle['enrollment'];

        $component = app(GradebookService::class)->addComponent($instructor, $offering, [
            'name' => 'Essay',
            'weight_percent' => 100,
            'kind' => ComponentKind::Assignment->value,
        ]);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'W1',
            'order' => 1,
        ]);
        $this->scoredAssignment($week, $component->id, $student->id, 80, 100);

        $fresh = app(GradebookService::class);
        $unpreloaded = $fresh->componentPercent($component->fresh(), $student);
        $this->assertEquals(80.0, $unpreloaded);

        $fresh->preloadOffering($offering);
        $this->assertEquals($unpreloaded, $fresh->componentPercent($component->fresh(), $student));
        $this->assertEquals(80.0, $fresh->computeEnrollment($enrollment->fresh())['percent']);
    }

    private function scoredAssignment(Week $week, string $componentId, string $studentId, float $final, float $max): Assignment
    {
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Assignment,
            'title' => 'Work '.$componentId,
            'order' => ContentItem::query()->where('week_id', $week->id)->count() + 1,
        ]);

        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'component_id' => $componentId,
            'instructions' => 'Do the work',
            'allowed_file_types' => ['pdf'],
            'max_points' => $max,
        ]);

        AssignmentSubmission::query()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $studentId,
            'text_body' => 'done',
            'submitted_at' => now(),
            'final_score' => $final,
            'attempt_no' => 1,
        ]);

        return $assignment;
    }
}
