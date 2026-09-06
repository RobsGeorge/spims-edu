<?php

namespace Tests\Feature\Projects;

use App\Exceptions\ConflictException;
use App\Models\ProjectMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamFormationTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function join_before_the_window_opens_fails(): void
    {
        $offering = $this->offering('TF1');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, [
            'join_opens_at' => now()->addHour(),
            'join_closes_at' => now()->addHours(2),
        ]);

        $this->expectException(ConflictException::class);
        $this->teams()->join($student, $assessment);
    }

    #[Test]
    public function join_at_capacity_fails(): void
    {
        $offering = $this->offering('TF2');
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $c = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);

        $team = $this->joinTeam($a, $assessment);
        $this->teams()->join($b, $assessment, $team->id);

        $this->expectException(ConflictException::class);
        $this->teams()->join($c, $assessment, $team->id);
    }

    #[Test]
    public function leave_once_succeeds_and_a_second_leave_fails(): void
    {
        $offering = $this->offering('TF3');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);

        $this->joinTeam($student, $assessment);
        $this->teams()->leave($student, $assessment);

        $this->assertNull($this->teams()->activeMembershipForAssessment($student, $assessment));

        $this->expectException(ConflictException::class);
        $this->teams()->leave($student, $assessment);
    }

    #[Test]
    public function leaving_frees_a_seat_immediately_and_rejoin_another_team_is_ok(): void
    {
        $offering = $this->offering('TF4');
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $c = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);

        $teamA = $this->joinTeam($a, $assessment);
        $this->teams()->join($b, $assessment, $teamA->id);
        $this->assertSame(2, $this->teams()->activeSeatCount($teamA));

        $this->teams()->leave($b, $assessment);
        $this->assertSame(1, $this->teams()->activeSeatCount($teamA->fresh()));

        $this->teams()->join($c, $assessment, $teamA->id);
        $this->assertSame(2, $this->teams()->activeSeatCount($teamA->fresh()));

        $teamB = $this->joinTeam($b, $assessment);
        $this->assertNotSame($teamA->id, $teamB->id);
        $this->assertNotNull($this->teams()->activeMembership($b, $teamB));
        $this->assertSame(1, ProjectMembership::query()->where('student_id', $b->id)->whereNull('left_at')->count());
    }
}
