<?php

namespace Tests\Feature\StaffUi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

class StaffArabicProjectViewportTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;
    use StaffArabicViewportAssertions;

    #[Test]
    public function arabic_project_announce_page_is_rtl_and_keeps_the_confirm_dialog(): void
    {
        $offering = $this->offering('ARPR');
        $instructor = $this->arabic($this->instructorOn($offering));
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $this->addCriterion($assessment);
        $team = $this->joinTeam($student, $assessment);
        $this->grading()->setTeamScore($instructor, $team, 88);

        $page = $this->actingAs($instructor)
            ->get(route('teach.projects.show', [$offering, $assessment]));
        $this->assertArabicShell($page);
        $page->assertSee(__('staff.projects.seating'), false)
            ->assertSee(__('staff.projects.announce'), false)
            ->assertSee('announceGradesModal', false)
            ->assertSee('modal-dialog-scrollable', false)
            ->assertSee('spims-staff-row', false)
            ->assertSee($team->name, false);
    }
}
