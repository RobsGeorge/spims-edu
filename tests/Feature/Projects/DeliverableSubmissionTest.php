<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectDeliverableKind;
use App\Exceptions\AuthorizationException;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeliverableSubmissionTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function file_count_and_size_limits_are_enforced(): void
    {
        Storage::fake('local');
        $offering = $this->offering('DS1');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $project = $this->joinTeam($student, $assessment);
        $deliverable = $this->fileDeliverable($assessment, ['max_files' => 1, 'max_file_mb' => 1]);

        try {
            $this->deliverables()->submit($student, $project, $deliverable, [], [
                UploadedFile::fake()->create('a.pdf', 100),
                UploadedFile::fake()->create('b.pdf', 100),
            ]);
            $this->fail('Expected file-count validation to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('files', $e->errors());
        }

        try {
            $this->deliverables()->submit($student, $project, $deliverable, [], [
                UploadedFile::fake()->create('big.pdf', 2048),
            ]);
            $this->fail('Expected file-size validation to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('files', $e->errors());
        }

        $this->assertSame(0, ProjectDeliverableSubmission::query()->count());
    }

    #[Test]
    public function link_and_text_kinds_submit(): void
    {
        $offering = $this->offering('DS2');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $project = $this->joinTeam($student, $assessment);
        $phase = $assessment->phases()->create(['name' => 'P', 'position' => 1]);

        $link = ProjectDeliverable::query()->create([
            'phase_id' => $phase->id,
            'kind' => ProjectDeliverableKind::Link,
            'title' => 'Repo',
            'due_at' => now()->addDay(),
        ]);
        $text = ProjectDeliverable::query()->create([
            'phase_id' => $phase->id,
            'kind' => ProjectDeliverableKind::Text,
            'title' => 'Abstract',
            'due_at' => now()->addDay(),
        ]);

        $linkSub = $this->deliverables()->submit($student, $project, $link, ['link' => 'https://example.com/repo']);
        $textSub = $this->deliverables()->submit($student, $project, $text, ['body' => 'Abstract text']);

        $this->assertSame('https://example.com/repo', $linkSub->link);
        $this->assertSame('Abstract text', $textSub->body);
        $this->assertFalse($linkSub->late);
        $this->assertFalse($textSub->late);
    }

    #[Test]
    public function a_file_can_be_replaced_and_deleting_another_teams_file_is_forbidden(): void
    {
        Storage::fake('local');
        $offering = $this->offering('DS3');
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 1]);
        $teamA = $this->joinTeam($a, $assessment);
        $teamB = $this->joinTeam($b, $assessment);
        $deliverable = $this->fileDeliverable($assessment);

        $subA = $this->deliverables()->submit($a, $teamA, $deliverable, [], [
            UploadedFile::fake()->create('first.pdf', 20),
        ]);
        $fileA = $subA->files()->first();
        $this->assertNotNull($fileA);

        $replaced = $this->deliverables()->replaceFile($a, $teamA, $fileA, UploadedFile::fake()->create('second.pdf', 30));
        $this->assertSame('second.pdf', $replaced->original_name);
        $this->assertSame(1, $subA->fresh()->files()->count());

        $subB = $this->deliverables()->submit($b, $teamB, $deliverable, [], [
            UploadedFile::fake()->create('other.pdf', 20),
        ]);
        $fileB = $subB->files()->first();

        $this->expectException(AuthorizationException::class);
        $this->deliverables()->deleteFile($a, $teamB, $fileB);
    }

    #[Test]
    public function submitting_after_the_due_date_is_flagged_late(): void
    {
        Storage::fake('local');
        $offering = $this->offering('DS4');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $project = $this->joinTeam($student, $assessment);
        $deliverable = $this->fileDeliverable($assessment, ['due_at' => now()->subHour()]);

        $submission = $this->deliverables()->submit($student, $project, $deliverable, [], [
            UploadedFile::fake()->create('late.pdf', 20),
        ]);

        $this->assertTrue($submission->late);
    }
}
