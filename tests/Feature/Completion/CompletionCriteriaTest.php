<?php

namespace Tests\Feature\Completion;

use App\Enums\CompletionCriterionKind;
use App\Enums\CompletionOutcome;
use App\Enums\RoleType;
use App\Models\CompletionResult;
use App\Models\EnrollmentItemCompletion;
use App\Models\User;
use App\Services\Completion\CompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompletionCriteriaTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function each_required_kind_independently_decides_the_outcome(): void
    {
        $offering = $this->offering('CRIT1');
        $admin = $this->admin();
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $enrollment = $this->enroll($student, $offering);
        $service = app(CompletionService::class);

        $this->setGrade($offering, $student, 90);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::Completed, CompletionResult::query()->first()->outcome);

        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinAttendance->value,
            'threshold' => 75,
            'is_required' => true,
        ]);
        $this->setAttendance($instructor, $offering, $student, 2, 5);
        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::NotCompleted, CompletionResult::query()->first()->outcome);

        $item = $this->requiredItem($offering, $enrollment, true);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::RequiredItem->value,
            'content_item_id' => $item->id,
            'is_required' => true,
        ]);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinDiscussion->value,
            'threshold' => 80,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);
        $result = CompletionResult::query()->first();
        $this->assertSame(CompletionOutcome::NotCompleted, $result->outcome);
        $this->assertTrue(collect($result->met_criteria)->contains(fn ($row) => $row['kind'] === 'MIN_GRADE' && $row['passed']));
        $this->assertTrue(collect($result->met_criteria)->contains(fn ($row) => $row['kind'] === 'MIN_ATTENDANCE' && ! $row['passed']));
        $this->assertTrue(collect($result->met_criteria)->contains(fn ($row) => $row['kind'] === 'REQUIRED_ITEM' && $row['passed']));
        $this->assertTrue(collect($result->met_criteria)->contains(fn ($row) => $row['kind'] === 'MIN_DISCUSSION' && $row['passed']));
    }

    #[Test]
    public function conjunction_of_required_grade_and_attendance_forces_non_completion(): void
    {
        $offering = $this->offering('CRIT2');
        $admin = $this->admin();
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 90);
        $this->setAttendance($instructor, $offering, $student, 2, 5);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinAttendance->value,
            'threshold' => 75,
            'is_required' => true,
        ]);

        $service->evaluate($admin, $offering);

        $this->assertSame(CompletionOutcome::NotCompleted, CompletionResult::query()->first()->outcome);
        $this->assertSame(1, CompletionResult::query()->count());
    }

    #[Test]
    public function a_failing_optional_criterion_does_not_block_completion(): void
    {
        $offering = $this->offering('CRIT3');
        $admin = $this->admin();
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 90);
        $this->setAttendance($instructor, $offering, $student, 2, 5);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinAttendance->value,
            'threshold' => 75,
            'is_required' => false,
        ]);

        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::Completed, CompletionResult::query()->first()->outcome);
    }

    #[Test]
    public function re_evaluating_unchanged_data_does_not_duplicate_rows(): void
    {
        $offering = $this->offering('CRIT4');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 80);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);

        $first = $service->evaluate($admin, $offering)->first();
        $second = $service->evaluate($admin, $offering)->first();

        $this->assertSame(1, CompletionResult::query()->count());
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->outcome, $second->outcome);
        $this->assertSame($first->met_criteria, $second->met_criteria);
    }

    #[Test]
    public function min_attendance_alone_decides_the_outcome(): void
    {
        $offering = $this->offering('CRIT5');
        $admin = $this->admin();
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setAttendance($instructor, $offering, $student, 5, 5);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinAttendance->value,
            'threshold' => 75,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::Completed, CompletionResult::query()->first()->outcome);
    }

    #[Test]
    public function required_item_alone_decides_the_outcome(): void
    {
        $offering = $this->offering('CRIT6');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $enrollment = $this->enroll($student, $offering);
        $item = $this->requiredItem($offering, $enrollment, false);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::RequiredItem->value,
            'content_item_id' => $item->id,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::NotCompleted, CompletionResult::query()->first()->outcome);

        EnrollmentItemCompletion::query()->create([
            'enrollment_id' => $enrollment->id,
            'content_item_id' => $item->id,
            'completed_at' => now(),
        ]);
        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::Completed, CompletionResult::query()->first()->outcome);
        $this->assertSame(1, CompletionResult::query()->count());
    }

    #[Test]
    public function min_discussion_alone_decides_the_outcome(): void
    {
        $offering = $this->offering('CRIT7');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 50);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinDiscussion->value,
            'threshold' => 80,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::NotCompleted, CompletionResult::query()->first()->outcome);

        $this->setGrade($offering, $student, 90);
        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::Completed, CompletionResult::query()->first()->outcome);
    }
}
