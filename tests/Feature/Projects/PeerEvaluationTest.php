<?php

namespace Tests\Feature\Projects;

use App\Exceptions\ConflictException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeerEvaluationTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function a_student_cannot_rate_themselves(): void
    {
        [$a, $project] = $this->pairOnTeam('PE1');

        try {
            $this->peers()->submit($a, $project, ['ratee_id' => $a->id, 'score' => 5]);
            $this->fail('Expected self-rating to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ratee_id', $e->errors());
        }
    }

    #[Test]
    public function a_student_cannot_rate_outside_their_team(): void
    {
        $offering = $this->offering('PE2');
        $a = $this->studentOn($offering);
        $outsider = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 1]);
        $teamA = $this->joinTeam($a, $assessment);
        $this->joinTeam($outsider, $assessment);

        try {
            $this->peers()->submit($a, $teamA, ['ratee_id' => $outsider->id, 'score' => 4]);
            $this->fail('Expected outside-team rating to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ratee_id', $e->errors());
        }
    }

    #[Test]
    public function a_student_cannot_submit_twice_or_after_close(): void
    {
        [$a, $b, $project, $instructor, $assessment] = $this->pairOnTeamFull('PE3');

        $this->peers()->submit($a, $project, ['ratee_id' => $b->id, 'score' => 4, 'comment' => 'solid']);

        try {
            $this->peers()->submit($a, $project, ['ratee_id' => $b->id, 'score' => 5]);
            $this->fail('Expected duplicate peer submit to fail.');
        } catch (ConflictException) {
            $this->assertTrue(true);
        }

        $this->peers()->close($instructor, $assessment);

        $this->expectException(ConflictException::class);
        $this->peers()->submit($b, $project, ['ratee_id' => $a->id, 'score' => 3]);
    }

    #[Test]
    public function aggregates_are_anonymous_to_staff(): void
    {
        [$a, $b, $project, $instructor, $assessment] = $this->pairOnTeamFull('PE4');
        $this->peers()->submit($a, $project, ['ratee_id' => $b->id, 'score' => 4, 'comment' => 'named']);
        $this->peers()->submit($b, $project, ['ratee_id' => $a->id, 'score' => 5, 'comment' => 'also']);

        $aggregates = $this->peers()->aggregatesForStaff($instructor, $assessment);
        $this->assertNotEmpty($aggregates);
        foreach ($aggregates as $row) {
            $this->assertArrayNotHasKey('rater_id', $row);
            $this->assertArrayNotHasKey('ratee_id', $row);
            $this->assertArrayHasKey('average_score', $row);
            $this->assertArrayHasKey('project_id', $row);
            $encoded = json_encode($row);
            $this->assertStringNotContainsString($a->id, $encoded);
            $this->assertStringNotContainsString($b->id, $encoded);
        }
    }

    /** @return array{0: User, 1: \App\Models\Project} */
    private function pairOnTeam(string $code): array
    {
        $offering = $this->offering($code);
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $project = $this->joinTeam($a, $assessment);
        $this->teams()->join($b, $assessment, $project->id);

        return [$a, $project];
    }

    /** @return array{0: User, 1: User, 2: \App\Models\Project, 3: User, 4: \App\Models\ProjectAssessment} */
    private function pairOnTeamFull(string $code): array
    {
        $offering = $this->offering($code);
        $instructor = $this->instructorOn($offering);
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $project = $this->joinTeam($a, $assessment);
        $this->teams()->join($b, $assessment, $project->id);

        return [$a, $b, $project, $instructor, $assessment];
    }
}
