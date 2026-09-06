<?php

namespace Tests\Feature\StaffUi;

use App\Enums\OfferingStaffRole;
use App\Enums\RoleType;
use App\Models\AssessmentResultAnnouncement;
use App\Models\AttemptAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\InstructorGradingFixtures;
use Tests\TestCase;

class StaffAssessmentUiTest extends TestCase
{
    use InstructorGradingFixtures;
    use RefreshDatabase;

    #[Test]
    public function instructor_grades_an_answer_from_the_teach_attempts_page(): void
    {
        $bundle = $this->gradingBundle('S8TA');
        $attempt = $this->submittedAttempt($bundle['instructor'], $bundle['offering'], $bundle['student']);
        $answer = $attempt['answer'];

        $this->actingAs($bundle['instructor'])
            ->get(route('teach.show', ['offering' => $bundle['offering'], 'tab' => 'assessments']))
            ->assertOk()
            ->assertSee($attempt['assessment']->title)
            ->assertSee(route('teach.assessments.attempts', [$bundle['offering'], $attempt['assessment']]), false);

        $this->actingAs($bundle['instructor'])
            ->get(route('teach.assessments.attempts', [$bundle['offering'], $attempt['assessment']]))
            ->assertOk()
            ->assertSee($bundle['student']->first_name)
            ->assertSee(__('assessment.override_score'));

        $this->actingAs($bundle['instructor'])
            ->from(route('teach.assessments.attempts', [$bundle['offering'], $attempt['assessment']]))
            ->post(route('teach.assessments.grade', [$bundle['offering'], $attempt['assessment'], $answer]), [
                'final_score' => 9,
                'feedback' => 'Adjusted',
            ])
            ->assertRedirect();

        $graded = AttemptAnswer::query()->findOrFail($answer->id);
        $this->assertEquals(9.0, (float) $graded->final_score);
        $this->assertSame('Adjusted', $graded->feedback);
        $this->assertSame($bundle['instructor']->id, $graded->graded_by_id);
    }

    #[Test]
    public function announce_requires_a_one_time_token(): void
    {
        $bundle = $this->gradingBundle('S8TN');
        $attempt = $this->submittedAttempt($bundle['instructor'], $bundle['offering'], $bundle['student']);
        $assessment = $attempt['assessment'];

        $this->actingAs($bundle['instructor'])
            ->from(route('teach.assessments.attempts', [$bundle['offering'], $assessment]))
            ->post(route('teach.assessments.announce', [$bundle['offering'], $assessment]))
            ->assertInvalid(['confirmation_token']);

        $this->assertSame(0, AssessmentResultAnnouncement::query()->where('assessment_id', $assessment->id)->count());

        $page = $this->actingAs($bundle['instructor'])
            ->get(route('teach.assessments.attempts', [$bundle['offering'], $assessment]));
        $page->assertOk();
        $this->assertSame(1, preg_match('/name="confirmation_token" value="([a-f0-9]+)"/', $page->getContent(), $matches));
        $token = $matches[1];

        $this->actingAs($bundle['instructor'])
            ->from(route('teach.assessments.attempts', [$bundle['offering'], $assessment]))
            ->post(route('teach.assessments.announce', [$bundle['offering'], $assessment]), [
                'confirmation_token' => $token,
            ])
            ->assertRedirect();

        $this->assertSame(1, AssessmentResultAnnouncement::query()->where('assessment_id', $assessment->id)->count());

        $this->actingAs($bundle['instructor'])
            ->from(route('teach.assessments.attempts', [$bundle['offering'], $assessment]))
            ->post(route('teach.assessments.announce', [$bundle['offering'], $assessment]), [
                'confirmation_token' => $token,
            ])
            ->assertInvalid(['confirmation_token']);
    }

    #[Test]
    public function ta_can_open_attempts_but_is_denied_announce(): void
    {
        $bundle = $this->gradingBundle('S8TT');
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $bundle['offering'], OfferingStaffRole::Ta);
        $attempt = $this->submittedAttempt($bundle['instructor'], $bundle['offering'], $bundle['student']);
        $assessment = $attempt['assessment'];

        $this->actingAs($ta)
            ->get(route('teach.assessments.attempts', [$bundle['offering'], $assessment]))
            ->assertOk()
            ->assertSee($bundle['student']->first_name)
            ->assertDontSee('name="confirmation_token"', false);

        $this->actingAs($ta)
            ->from(route('teach.assessments.attempts', [$bundle['offering'], $assessment]))
            ->post(route('teach.assessments.announce', [$bundle['offering'], $assessment]), [
                'confirmation_token' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden();

        $this->assertSame(0, AssessmentResultAnnouncement::query()->where('assessment_id', $assessment->id)->count());
    }

    #[Test]
    public function cross_offering_attempts_and_grade_are_forbidden(): void
    {
        $bundleA = $this->gradingBundle('S8TX');
        $bundleB = $this->gradingBundle('S8TY');
        $attempt = $this->submittedAttempt($bundleA['instructor'], $bundleA['offering'], $bundleA['student']);

        $this->actingAs($bundleB['instructor'])
            ->get(route('teach.assessments.attempts', [$bundleA['offering'], $attempt['assessment']]))
            ->assertForbidden();

        $this->actingAs($bundleB['instructor'])
            ->get(route('teach.assessments.attempts', [$bundleB['offering'], $attempt['assessment']]))
            ->assertForbidden();

        $this->actingAs($bundleB['instructor'])
            ->post(route('teach.assessments.grade', [$bundleB['offering'], $attempt['assessment'], $attempt['answer']]), [
                'final_score' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($bundleB['instructor'])
            ->post(route('teach.assessments.announce', [$bundleB['offering'], $attempt['assessment']]), [
                'confirmation_token' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden();

        $fresh = AttemptAnswer::query()->findOrFail($attempt['answer']->id);
        $this->assertEquals((float) $attempt['answer']->final_score, (float) $fresh->final_score);
        $this->assertSame(0, AssessmentResultAnnouncement::query()->where('assessment_id', $attempt['assessment']->id)->count());
    }
}
