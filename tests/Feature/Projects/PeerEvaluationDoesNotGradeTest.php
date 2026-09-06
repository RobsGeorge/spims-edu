<?php

namespace Tests\Feature\Projects;

use App\Models\ProjectGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeerEvaluationDoesNotGradeTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function peer_submit_leaves_project_grades_byte_for_byte_unchanged_and_announce_ignores_peer_scores(): void
    {
        $offering = $this->offering('PDG1');
        $instructor = $this->instructorOn($offering);
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $component = $this->projectComponent($offering);
        $assessment = $this->publishedAssessment($offering, [
            'component_id' => $component->id,
            'team_size_max' => 2,
        ]);
        $this->addCriterion($assessment);
        $project = $this->joinTeam($a, $assessment);
        $this->teams()->join($b, $assessment, $project->id);

        $this->grading()->setTeamScore($instructor, $project, 90);
        $before = DB::table('project_grades')->orderBy('id')->get()->toJson();
        $this->assertNotSame('[]', $before);

        $this->peers()->submit($a, $project, ['ratee_id' => $b->id, 'score' => 1, 'comment' => 'low']);
        $this->peers()->submit($b, $project, ['ratee_id' => $a->id, 'score' => 2, 'comment' => 'also low']);

        $afterSubmit = DB::table('project_grades')->orderBy('id')->get()->toJson();
        $this->assertSame($before, $afterSubmit);
        $this->assertSame(0, ProjectGrade::query()->where('score', 1)->count());
        $this->assertSame(0, ProjectGrade::query()->where('score', 2)->count());

        $this->grading()->announce($instructor, $assessment);

        $this->assertSame(90.0, $this->grading()->announcedPercentForStudent($assessment, $a));
        $this->assertSame(90.0, $this->grading()->announcedPercentForStudent($assessment, $b));
        $this->assertSame(0, ProjectGrade::query()->whereIn('score', [1, 2])->count());
        $this->assertSame(
            $before !== '[]',
            ProjectGrade::query()->where('project_assessment_id', $assessment->id)->where('score', 90)->exists()
        );
    }
}
