<?php

namespace Tests\Feature\Completion;

use App\Enums\CompletionCriterionKind;
use App\Enums\CompletionOutcome;
use App\Enums\RoleType;
use App\Models\User;
use App\Services\Completion\CompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentCompletionWebTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    private function addMinGradeAndEvaluate($offering, User $admin, User $student, float $percent = 90): void
    {
        $this->setGrade($offering, $student, $percent);
        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);
    }

    #[Test]
    public function an_enrolled_student_sees_pending_then_own_completed_result(): void
    {
        $offering = $this->offering('WEB1');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $pending = $this->actingAs($student)
            ->get(route('offerings.completion', $offering));

        $pending->assertOk()
            ->assertSee(__('completion.own_title'))
            ->assertSee(__('completion.outcome_'.CompletionOutcome::Pending->value))
            ->assertSee(__('completion.not_yet_evaluated'))
            ->assertSee(__('completion.own_pending'))
            ->assertDontSee(__('completion.evaluate'))
            ->assertDontSee(route('api.v1.offerings.completion.evaluate', $offering), false);

        $this->addMinGradeAndEvaluate($offering, $admin, $student);

        $completed = $this->actingAs($student)
            ->get(route('offerings.completion', $offering));

        $completed->assertOk()
            ->assertSee(__('completion.outcome_'.CompletionOutcome::Completed->value))
            ->assertSee(__('completion.kind_MIN_GRADE'))
            ->assertSee(__('completion.passed'))
            ->assertDontSee(__('completion.not_yet_evaluated'))
            ->assertDontSee(__('completion.evaluate'));
    }

    #[Test]
    public function a_student_who_is_not_enrolled_gets_404(): void
    {
        $mine = $this->offering('WEB2');
        $theirs = $this->offering('WEB3');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $mine);

        $this->actingAs($student)
            ->get(route('offerings.completion', $theirs))
            ->assertNotFound();
    }

    #[Test]
    public function course_player_and_learn_offering_link_to_own_completion(): void
    {
        $offering = $this->offering('WEB4');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $completionUrl = route('offerings.completion', $offering);

        $this->actingAs($student)
            ->get(route('courses.player', $offering))
            ->assertOk()
            ->assertSee(__('completion.nav'))
            ->assertSee($completionUrl, false);

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee(__('completion.nav'))
            ->assertSee($completionUrl, false);
    }
}
