<?php

namespace Tests\Feature\Api;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

class ProjectsApiTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function join_returns_409_outside_the_window_or_at_capacity(): void
    {
        $offering = $this->offering('PA1');
        $student = $this->studentOn($offering);
        $early = $this->publishedAssessment($offering, [
            'join_opens_at' => now()->addHour(),
            'join_closes_at' => now()->addHours(2),
        ]);

        $this->asApi($student)
            ->postJson(route('api.v1.project-assessments.join', $early))
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');

        $open = $this->publishedAssessment($offering, ['title' => 'Open', 'team_size_max' => 1]);
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $projectId = $this->asApi($a)
            ->postJson(route('api.v1.project-assessments.join', $open))
            ->assertCreated()
            ->json('data.project_id');

        $this->asApi($b)
            ->postJson(route('api.v1.project-assessments.join', $open), ['project_id' => $projectId])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    #[Test]
    public function leave_is_allowed_once_on_the_api(): void
    {
        $offering = $this->offering('PA2');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $this->joinTeam($student, $assessment);

        $this->asApi($student)
            ->postJson(route('api.v1.project-assessments.leave', $assessment))
            ->assertOk();

        $this->asApi($student)
            ->postJson(route('api.v1.project-assessments.leave', $assessment))
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    #[Test]
    public function multipart_submit_and_file_delete_round_trip(): void
    {
        Storage::fake('local');
        $offering = $this->offering('PA3');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $project = $this->joinTeam($student, $assessment);
        $deliverable = $this->fileDeliverable($assessment);

        $created = $this->asApi($student)
            ->post(route('api.v1.projects.deliverables.submit', [$project, $deliverable]), [
                'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $fileId = $created->json('data.files.0.id');
        $this->assertNotNull($fileId);

        $this->asApi($student)
            ->deleteJson(route('api.v1.projects.submission-files.destroy', [$project, $fileId]))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    #[Test]
    public function unenrolled_student_cannot_join(): void
    {
        $offering = $this->offering('PA4');
        $stranger = User::factory()->withRole(RoleType::Student)->create();
        $assessment = $this->publishedAssessment($offering);

        $this->asApi($stranger)
            ->postJson(route('api.v1.project-assessments.join', $assessment))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
