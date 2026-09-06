<?php

namespace Tests\Feature\Projects;

use App\Enums\EnrollmentStatus;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Services\Gradebook\GradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectGradingTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function criteria_weights_must_sum_to_100(): void
    {
        $offering = $this->offering('PG1');
        $instructor = $this->instructorOn($offering);
        $assessment = $this->publishedAssessment($offering);

        try {
            $this->grading()->setCriteria($instructor, $assessment, [
                ['name' => 'A', 'weight' => 60],
                ['name' => 'B', 'weight' => 50],
            ]);
            $this->fail('Expected weights validation to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('criteria', $e->errors());
        }

        $rows = $this->grading()->setCriteria($instructor, $assessment, [
            ['name' => 'Quality', 'weight' => 60],
            ['name' => 'Process', 'weight' => 40],
        ]);
        $this->assertCount(2, $rows);
        $this->assertEquals(100.0, collect($rows)->sum(fn ($c) => (float) $c->weight));
    }

    #[Test]
    public function per_student_override_beats_the_team_score_in_the_gradebook(): void
    {
        $offering = $this->offering('PG2');
        $instructor = $this->instructorOn($offering);
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $component = $this->projectComponent($offering);
        $assessment = $this->publishedAssessment($offering, ['component_id' => $component->id, 'team_size_max' => 2]);
        $this->addCriterion($assessment);
        $team = $this->joinTeam($a, $assessment);
        $this->teams()->join($b, $assessment, $team->id);

        $this->grading()->setTeamScore($instructor, $team, 80);
        $this->grading()->setStudentOverride($instructor, $assessment, $a, 95);

        $gradebook = app(GradebookService::class);
        $enrollA = Enrollment::query()->where('student_id', $a->id)->where('offering_id', $offering->id)->first();
        $this->assertNull($gradebook->computeEnrollment($enrollA)['components'][0]['score']);

        $this->grading()->announce($instructor, $assessment);

        $enrollB = Enrollment::query()->where('student_id', $b->id)->where('offering_id', $offering->id)->first();

        $computedA = $gradebook->computeEnrollment($enrollA);
        $computedB = $gradebook->computeEnrollment($enrollB);

        $this->assertSame(95.0, $computedA['components'][0]['score']);
        $this->assertSame(80.0, $computedB['components'][0]['score']);
        $this->assertSame(EnrollmentStatus::Enrolled, $enrollA->status);
    }

    #[Test]
    public function announce_is_idempotent(): void
    {
        $offering = $this->offering('PG3');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $component = $this->projectComponent($offering);
        $assessment = $this->publishedAssessment($offering, ['component_id' => $component->id]);
        $this->addCriterion($assessment);
        $team = $this->joinTeam($student, $assessment);

        $this->grading()->setTeamScore($instructor, $team, 88);
        $first = $this->grading()->announce($instructor, $assessment);
        $announcedAt = $team->grades()->whereNull('student_id')->whereNull('criterion_id')->first()->announced_at;
        $this->assertGreaterThan(0, $first);

        $second = $this->grading()->announce($instructor, $assessment);
        $this->assertSame(0, $second);
        $this->assertEquals(
            $announcedAt->toIso8601String(),
            $team->grades()->whereNull('student_id')->whereNull('criterion_id')->first()->fresh()->announced_at->toIso8601String()
        );
        $this->assertSame(1, AuditLog::query()->where('action', 'projects.announce')->count());
    }
}
