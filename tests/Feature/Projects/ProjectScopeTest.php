<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectDeliverableKind;
use App\Exceptions\AuthorizationException;
use App\Models\ProjectDeliverable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectScopeTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function cross_offering_and_cross_team_access_is_denied(): void
    {
        Storage::fake('local');

        $offeringA = $this->offering('SC1');
        $offeringB = $this->offering('SC2');
        $instructorA = $this->instructorOn($offeringA);
        $instructorB = $this->instructorOn($offeringB);
        $studentA = $this->studentOn($offeringA);
        $studentB = $this->studentOn($offeringB);
        $peerOnA = $this->studentOn($offeringA);

        $assessmentA = $this->publishedAssessment($offeringA, ['team_size_max' => 1]);
        $assessmentB = $this->publishedAssessment($offeringB, ['team_size_max' => 1]);
        $teamA = $this->joinTeam($studentA, $assessmentA);
        $teamA2 = $this->joinTeam($peerOnA, $assessmentA);
        $teamB = $this->joinTeam($studentB, $assessmentB);

        $phase = $assessmentA->phases()->create(['name' => 'P', 'position' => 1, 'due_at' => now()->addDay()]);
        $deliverable = ProjectDeliverable::query()->create([
            'phase_id' => $phase->id,
            'kind' => ProjectDeliverableKind::Text,
            'title' => 'Notes',
            'due_at' => now()->addDay(),
        ]);

        $this->expectException(AuthorizationException::class);
        $this->teams()->join($studentA, $assessmentB);
    }

    #[Test]
    public function student_api_hides_other_teams_on_read_and_forbids_them_on_write(): void
    {
        Storage::fake('local');
        $offering = $this->offering('SC3');
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 1]);
        $teamA = $this->joinTeam($a, $assessment);
        $teamB = $this->joinTeam($b, $assessment);
        $deliverable = $this->fileDeliverable($assessment);
        $subB = $this->deliverables()->submit($b, $teamB, $deliverable, [], [
            UploadedFile::fake()->create('b.pdf', 10),
        ]);
        $fileB = $subB->files()->first();

        $this->asApi($a)
            ->getJson('/api/v1/projects/'.$teamB->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->asApi($a)
            ->post('/api/v1/projects/'.$teamB->id.'/deliverables/'.$deliverable->id.'/submit', [
                'file' => UploadedFile::fake()->create('hijack.pdf', 10),
            ], ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->asApi($a)
            ->deleteJson('/api/v1/projects/'.$teamB->id.'/submission-files/'.$fileB->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->asApi($a)
            ->getJson('/api/v1/projects/'.$teamB->id.'/peer-evaluations/pending')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->asApi($a)
            ->postJson('/api/v1/projects/'.$teamB->id.'/peer-evaluations', [
                'ratee_id' => $b->id,
                'score' => 3,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    #[Test]
    public function staff_cannot_manage_another_offerings_projects(): void
    {
        $mine = $this->offering('SC4');
        $theirs = $this->offering('SC5');
        $instructor = $this->instructorOn($mine);
        $theirInstructor = $this->instructorOn($theirs);
        $assessment = $this->publishedAssessment($theirs);
        $this->addCriterion($assessment);
        $student = $this->studentOn($theirs);
        $team = $this->joinTeam($student, $assessment);
        $this->grading()->setTeamScore($theirInstructor, $team, 70);

        $this->expectException(AuthorizationException::class);
        $this->grading()->announce($instructor, $assessment);
    }

    #[Test]
    public function student_cannot_read_another_offerings_assessments(): void
    {
        $mine = $this->offering('SC6');
        $theirs = $this->offering('SC7');
        $student = $this->studentOn($mine);
        $this->publishedAssessment($theirs);

        $this->asApi($student)
            ->getJson('/api/v1/offerings/'.$theirs->id.'/project-assessments')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    private function asApi(User $user)
    {
        Auth::forgetGuards();

        return $this->withToken($user->createToken('api', ['role:STUDENT'])->plainTextToken);
    }
}
