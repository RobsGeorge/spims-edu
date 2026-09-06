<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectDeliverableKind;
use App\Models\AuditLog;
use App\Models\ProjectDeliverable;
use App\Models\ProjectMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

class StudentWaveEProjectsTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function student_completes_the_project_surface_end_to_end(): void
    {
        Storage::fake('local');
        $offering = $this->offering('WE1');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $teammate = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $phase = $assessment->phases()->create(['name' => 'Build', 'position' => 1, 'due_at' => now()->addDay()]);
        $text = ProjectDeliverable::query()->create([
            'phase_id' => $phase->id,
            'kind' => ProjectDeliverableKind::Text,
            'title' => 'Abstract',
            'due_at' => now()->addDay(),
        ]);

        $list = $this->asApi($student)
            ->getJson(route('api.v1.offerings.project-assessments', $offering))
            ->assertOk()
            ->json('data');
        $this->assertSame($assessment->id, $list[0]['id']);
        $this->assertNull($list[0]['project']);

        $joined = $this->asApi($student)
            ->postJson(route('api.v1.project-assessments.join', $assessment), [], [
                'Idempotency-Key' => 'join-we1',
            ])
            ->assertCreated()
            ->json('data');

        $projectId = $joined['project_id'];
        $this->assertNotNull($projectId);
        $this->assertSame(1, AuditLog::query()->where('action', 'projects.join')->count());

        $this->asApi($student)
            ->postJson(route('api.v1.project-assessments.join', $assessment), [], [
                'Idempotency-Key' => 'join-we1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.project_id', $projectId);
        $this->assertSame(1, ProjectMembership::query()->where('student_id', $student->id)->count());

        $this->teams()->join($teammate, $assessment, $projectId);

        $show = $this->asApi($student)
            ->getJson(route('api.v1.projects.show', $projectId))
            ->assertOk()
            ->json('data');
        $this->assertSame($projectId, $show['id']);
        $this->assertCount(2, $show['members']);
        $this->assertSame('Abstract', $show['phases'][0]['deliverables'][0]['title']);

        $this->asApi($student)
            ->postJson(route('api.v1.projects.deliverables.submit', [$projectId, $text]), [
                'body' => 'Our abstract',
            ], ['Idempotency-Key' => 'sub-we1'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Our abstract')
            ->assertJsonPath('data.late', false);

        $pending = $this->asApi($student)
            ->getJson(route('api.v1.projects.peer-evaluations.pending', $projectId))
            ->assertOk()
            ->json('data');
        $this->assertSame($teammate->id, $pending[0]['id']);

        $this->asApi($student)
            ->postJson(route('api.v1.projects.peer-evaluations.store', $projectId), [
                'ratee_id' => $teammate->id,
                'score' => 5,
                'comment' => 'Great teammate',
            ])
            ->assertCreated()
            ->assertJsonPath('data.ratee_id', $teammate->id);

        $this->asApi($student)
            ->getJson(route('api.v1.projects.peer-evaluations.pending', $projectId))
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->asApi($student)
            ->postJson(route('api.v1.project-assessments.leave', $assessment))
            ->assertOk()
            ->assertJsonPath('data.project_id', $projectId);

        $this->assertNull($this->teams()->activeMembershipForAssessment($student, $assessment));

        $this->asApi($instructor, 'INSTRUCTOR')
            ->getJson(route('api.v1.teach.offerings.project-assessments', $offering))
            ->assertOk()
            ->assertJsonPath('data.0.id', $assessment->id);
    }
}
