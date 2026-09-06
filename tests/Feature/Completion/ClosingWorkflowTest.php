<?php

namespace Tests\Feature\Completion;

use App\Enums\CompletionCriterionKind;
use App\Enums\CompletionOutcome;
use App\Enums\OfferingClosingStatus;
use App\Enums\RoleType;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\CompletionResult;
use App\Models\User;
use App\Services\Completion\CompletionService;
use App\Services\Completion\OfferingClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClosingWorkflowTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function the_status_machine_only_advances_forward(): void
    {
        $offering = $this->offering('CLOSE1');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 90);

        $closing = app(OfferingClosingService::class);

        $this->expectException(ValidationException::class);
        $closing->announce($admin, $offering);
    }

    #[Test]
    public function closing_with_unlocked_grades_fails(): void
    {
        $offering = $this->offering('CLOSE5');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 90);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);

        $this->expectException(ValidationException::class);
        app(OfferingClosingService::class)->close($admin, $offering);
    }

    #[Test]
    public function lock_announce_close_advances_in_order_and_retries_are_idempotent(): void
    {
        $offering = $this->offering('CLOSE2');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 90);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);

        $closing = app(OfferingClosingService::class);
        $this->assertSame(OfferingClosingStatus::Open, $closing->statusFor($offering));

        $closing->lockGrading($admin, $offering);
        $this->assertSame(OfferingClosingStatus::GradingLocked, $closing->statusFor($offering));

        $closing->announce($admin, $offering);
        $closing->announce($admin, $offering);
        $this->assertSame(1, Announcement::query()->where('offering_id', $offering->id)->count());
        $this->assertSame(OfferingClosingStatus::Announced, $closing->statusFor($offering));

        $closing->close($admin, $offering);
        $this->assertSame(OfferingClosingStatus::Closed, $closing->statusFor($offering));

        $this->expectException(ValidationException::class);
        $closing->lockGrading($admin, $offering);
    }

    #[Test]
    public function grace_marks_change_outcomes_and_are_audited(): void
    {
        $offering = $this->offering('CLOSE3');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 68);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);
        $this->assertSame(CompletionOutcome::NotCompleted, CompletionResult::query()->first()->outcome);

        $closing = app(OfferingClosingService::class);
        $closing->lockGrading($admin, $offering);
        $closing->applyGraceMarks($admin, $offering, [$student->id => 5]);

        $this->assertSame(CompletionOutcome::Completed, CompletionResult::query()->first()->outcome);
        $this->assertTrue(AuditLog::query()->where('action', 'offering_closing.apply_grace_marks')->exists());
    }

    #[Test]
    public function an_instructor_cannot_close_an_offering(): void
    {
        $offering = $this->offering('CLOSE4');
        $admin = $this->admin();
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 90);

        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);

        $closing = app(OfferingClosingService::class);
        $closing->lockGrading($instructor, $offering);
        $closing->announce($instructor, $offering);

        $this->expectException(\App\Exceptions\AuthorizationException::class);
        $closing->close($instructor, $offering);
    }
}
