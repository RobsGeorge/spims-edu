<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectChangeRequestKind;
use App\Enums\ProjectChangeRequestStatus;
use App\Exceptions\ConflictException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectChangeRequestTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function a_student_can_raise_and_staff_can_reject(): void
    {
        $offering = $this->offering('CR1');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $other = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 1]);
        $this->joinTeam($student, $assessment);
        $target = $this->joinTeam($other, $assessment);

        $request = $this->changeRequests()->raise($student, $assessment, [
            'kind' => ProjectChangeRequestKind::Move->value,
            'reason' => 'Need to switch',
            'target_project_id' => $target->id,
        ]);
        $this->assertSame(ProjectChangeRequestStatus::Pending, $request->status);

        $rejected = $this->changeRequests()->reject($instructor, $request, 'Stay put');
        $this->assertSame(ProjectChangeRequestStatus::Rejected, $rejected->status);
        $this->assertSame($instructor->id, $rejected->decided_by_id);
        $this->assertNotNull($this->teams()->activeMembership($student, $this->teams()->activeMembershipForAssessment($student, $assessment)->project));
    }

    #[Test]
    public function approve_applies_the_change_atomically(): void
    {
        $offering = $this->offering('CR2');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $host = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $from = $this->openTeam($assessment, 'Alpha');
        $to = $this->openTeam($assessment, 'Beta');
        $this->teams()->join($student, $assessment, $from->id);
        $this->teams()->join($host, $assessment, $to->id);

        $request = $this->changeRequests()->raise($student, $assessment, [
            'kind' => ProjectChangeRequestKind::Move->value,
            'reason' => 'Move me',
            'target_project_id' => $to->id,
        ]);

        $approved = $this->changeRequests()->approve($instructor, $request, 'ok');

        $this->assertSame(ProjectChangeRequestStatus::Approved, $approved->status);
        $this->assertNull($this->teams()->activeMembership($student, $from));
        $this->assertNotNull($this->teams()->activeMembership($student, $to));
        $this->assertSame(2, $this->teams()->activeSeatCount($to->fresh()));
        $this->assertSame(0, $this->teams()->activeSeatCount($from->fresh()));
    }

    #[Test]
    public function approve_does_not_mark_approved_when_apply_fails(): void
    {
        $offering = $this->offering('CR3');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $host = $this->studentOn($offering);
        $filler = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $from = $this->openTeam($assessment, 'Alpha');
        $full = $this->openTeam($assessment, 'Beta');
        $this->teams()->join($student, $assessment, $from->id);
        $this->teams()->join($host, $assessment, $full->id);
        $this->teams()->join($filler, $assessment, $full->id);
        $this->assertNotSame($from->id, $full->id);
        $this->assertSame(2, $this->teams()->activeSeatCount($full));

        $request = $this->changeRequests()->raise($student, $assessment, [
            'kind' => ProjectChangeRequestKind::Move->value,
            'reason' => 'No room',
            'target_project_id' => $full->id,
        ]);

        try {
            $this->changeRequests()->approve($instructor, $request);
            $this->fail('Expected capacity conflict.');
        } catch (ConflictException) {
            $this->assertSame(ProjectChangeRequestStatus::Pending, $request->fresh()->status);
        }

        $this->assertNotNull($this->teams()->activeMembership($student, $from));
        $this->assertNull($this->teams()->activeMembership($student, $full));
        $this->assertSame(2, $this->teams()->activeSeatCount($full->fresh()));
    }
}
