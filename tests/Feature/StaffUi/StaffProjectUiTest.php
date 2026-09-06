<?php

namespace Tests\Feature\StaffUi;

use App\Models\ProjectGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

class StaffProjectUiTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function seating_is_visible_and_announce_requires_a_one_time_token(): void
    {
        $offering = $this->offering('S8PR');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $this->addCriterion($assessment);
        $team = $this->joinTeam($student, $assessment);
        $this->grading()->setTeamScore($instructor, $team, 88);

        $this->actingAs($instructor)
            ->get(route('teach.projects.show', [$offering, $assessment]))
            ->assertOk()
            ->assertSee($team->name)
            ->assertSee($student->first_name);

        $this->actingAs($instructor)
            ->from(route('teach.projects.show', [$offering, $assessment]))
            ->post(route('teach.projects.announce', [$offering, $assessment]))
            ->assertInvalid(['confirmation_token']);

        $this->assertSame(0, ProjectGrade::query()->whereNotNull('announced_at')->count());

        $page = $this->actingAs($instructor)
            ->get(route('teach.projects.show', [$offering, $assessment]));
        $page->assertOk();
        $this->assertSame(1, preg_match('/name="confirmation_token" value="([a-f0-9]+)"/', $page->getContent(), $matches));
        $token = $matches[1];

        $this->actingAs($instructor)
            ->from(route('teach.projects.show', [$offering, $assessment]))
            ->post(route('teach.projects.announce', [$offering, $assessment]), [
                'confirmation_token' => $token,
            ])
            ->assertRedirect();

        $this->assertSame(1, ProjectGrade::query()->whereNotNull('announced_at')->count());

        $this->actingAs($instructor)
            ->from(route('teach.projects.show', [$offering, $assessment]))
            ->post(route('teach.projects.announce', [$offering, $assessment]), [
                'confirmation_token' => $token,
            ])
            ->assertInvalid(['confirmation_token']);
    }

    #[Test]
    public function instructor_cannot_open_another_instructors_project_page(): void
    {
        $offeringA = $this->offering('S8PA');
        $offeringB = $this->offering('S8PB');
        $instructorA = $this->instructorOn($offeringA);
        $instructorB = $this->instructorOn($offeringB);
        $assessment = $this->publishedAssessment($offeringA);

        $this->actingAs($instructorB)
            ->get(route('teach.projects.show', [$offeringA, $assessment]))
            ->assertForbidden();

        $this->actingAs($instructorA)
            ->get(route('teach.projects.show', [$offeringA, $assessment]))
            ->assertOk();
    }
}
