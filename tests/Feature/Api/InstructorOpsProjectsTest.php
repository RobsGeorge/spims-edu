<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectDeliverableKind;
use App\Enums\ProjectReviewStatus;
use App\Models\ProjectDeliverable;
use App\Models\ProjectGrade;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

class InstructorOpsProjectsTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
    }

    #[Test]
    public function instructor_moves_a_member_between_teams(): void
    {
        $offering = $this->offering('OP1');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $from = $this->joinTeam($student, $assessment);
        $to = $this->openTeam($assessment, 'Team B');

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.projects.members.move', $from), [
                'student_id' => $student->id,
                'to_project_id' => $to->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.project_id', $to->id)
            ->assertJsonPath('data.student_id', $student->id);

        $this->assertNull($this->teams()->activeMembership($student, $from));
        $this->assertNotNull($this->teams()->activeMembership($student, $to));
    }

    #[Test]
    public function instructor_reviews_a_project_submission(): void
    {
        $offering = $this->offering('OP2');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $project = $this->joinTeam($student, $assessment);
        $phase = $assessment->phases()->create(['name' => 'P', 'position' => 1]);
        $deliverable = ProjectDeliverable::query()->create([
            'phase_id' => $phase->id,
            'kind' => ProjectDeliverableKind::Text,
            'title' => 'Abstract',
            'due_at' => now()->addDay(),
        ]);
        $submission = $this->deliverables()->submit($student, $project, $deliverable, ['body' => 'Draft']);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.project-submissions.review', $submission), [
                'review_status' => ProjectReviewStatus::Accepted->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.review_status', ProjectReviewStatus::Accepted->value)
            ->assertJsonPath('data.reviewer_id', $instructor->id);
    }

    #[Test]
    public function instructor_grades_a_team(): void
    {
        $offering = $this->offering('OP3');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $this->addCriterion($assessment);
        $project = $this->joinTeam($student, $assessment);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.projects.grade', $project), [
                'team_score' => 88,
            ])
            ->assertOk()
            ->assertJsonPath('data.team.score', 88)
            ->assertJsonPath('data.team.project_id', $project->id);

        $this->assertTrue(
            ProjectGrade::query()
                ->where('project_id', $project->id)
                ->whereNull('student_id')
                ->where('score', 88)
                ->exists()
        );
    }

    #[Test]
    public function announce_requires_confirmation_and_consumes_the_token_once(): void
    {
        $offering = $this->offering('OP4');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $this->addCriterion($assessment);
        $project = $this->joinTeam($student, $assessment);
        $this->grading()->setTeamScore($instructor, $project, 90);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.project-assessments.announce', $assessment))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['confirmation']]);

        $token = $this->asApi($instructor, 'INSTRUCTOR')
            ->getJson(route('api.v1.teach.project-assessments.teams', $assessment))
            ->assertOk()
            ->json('confirmation');

        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.project-assessments.announce', $assessment), [
                'confirmation' => $token,
            ])
            ->assertOk()
            ->assertJsonPath('data.announced', 1);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.project-assessments.announce', $assessment), [
                'confirmation' => $token,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['confirmation']]);
    }
}
