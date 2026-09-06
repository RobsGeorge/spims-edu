<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectDeliverableKind;
use App\Enums\RoleType;
use App\Models\ProjectDeliverable;
use App\Models\ProjectGrade;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentProjectUiTest extends TestCase
{
    use ProjectFixtures;
    use RefreshDatabase;

    #[Test]
    public function enrolled_student_joins_submits_and_leaves(): void
    {
        $offering = $this->offering('WSP1');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $phase = $assessment->phases()->create(['name' => 'Build', 'position' => 1, 'due_at' => now()->addDay()]);
        $text = ProjectDeliverable::query()->create([
            'phase_id' => $phase->id,
            'kind' => ProjectDeliverableKind::Text,
            'title' => 'Abstract',
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($student)
            ->get(route('student.projects.index', $offering))
            ->assertOk()
            ->assertSee($assessment->title)
            ->assertSee(__('projects.join_any'))
            ->assertSee(__('projects.team_size', ['min' => 1, 'max' => 2]));

        $this->actingAs($student)
            ->from(route('student.projects.index', $offering))
            ->post(route('student.projects.join', [$offering, $assessment]))
            ->assertRedirect();

        $membership = $this->teams()->activeMembershipForAssessment($student, $assessment);
        $this->assertNotNull($membership);
        $project = $membership->project()->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.projects.mine'))
            ->assertOk()
            ->assertSee($project->name)
            ->assertSee($assessment->title);

        $this->actingAs($student)
            ->get(route('student.projects.show', $project))
            ->assertOk()
            ->assertSee($student->first_name)
            ->assertSee('Abstract')
            ->assertSee(__('projects.peer_anonymous'));

        $this->actingAs($student)
            ->from(route('student.projects.show', $project))
            ->post(route('student.projects.submit', [$project, $text]), [
                'body' => 'Our abstract',
            ])
            ->assertRedirect(route('student.projects.show', $project));

        $this->actingAs($student)
            ->get(route('student.projects.show', $project))
            ->assertOk()
            ->assertSee('Our abstract')
            ->assertSee(__('projects.submitted'));

        $this->actingAs($student)
            ->from(route('student.projects.show', $project))
            ->post(route('student.projects.leave', [$offering, $assessment]))
            ->assertRedirect(route('student.projects.index', $offering));

        $this->assertNull($this->teams()->activeMembershipForAssessment($student, $assessment));
        $this->assertSame(1, ProjectMembership::query()->where('student_id', $student->id)->count());
    }

    #[Test]
    public function student_can_join_a_specific_open_team(): void
    {
        $offering = $this->offering('WSP2');
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering, ['team_size_max' => 2]);
        $team = $this->joinTeam($a, $assessment);

        $this->actingAs($b)
            ->get(route('student.projects.index', $offering))
            ->assertOk()
            ->assertSee($team->name)
            ->assertSee(__('projects.join_team'));

        $this->actingAs($b)
            ->from(route('student.projects.index', $offering))
            ->post(route('student.projects.join', [$offering, $assessment]), [
                'project_id' => $team->id,
            ])
            ->assertRedirect(route('student.projects.show', $team));

        $this->assertNotNull($this->teams()->activeMembership($b, $team));
    }

    #[Test]
    public function leave_is_allowed_once_and_then_rejected(): void
    {
        $offering = $this->offering('WSP3');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $this->joinTeam($student, $assessment);

        $this->actingAs($student)
            ->from(route('student.projects.index', $offering))
            ->post(route('student.projects.leave', [$offering, $assessment]))
            ->assertRedirect(route('student.projects.index', $offering));

        $this->actingAs($student)
            ->from(route('student.projects.index', $offering))
            ->post(route('student.projects.leave', [$offering, $assessment]))
            ->assertRedirect(route('student.projects.index', $offering))
            ->assertSessionHas('error', __('projects.leave_used'));
    }

    #[Test]
    public function student_cannot_see_another_team_or_unpublished_assessment(): void
    {
        $offering = $this->offering('WSP4');
        $a = $this->studentOn($offering);
        $b = $this->studentOn($offering);
        $outsider = User::factory()->withRole(RoleType::Student)->create();
        $published = $this->publishedAssessment($offering, ['title' => 'Visible', 'team_size_max' => 1]);
        $draft = $this->publishedAssessment($offering, [
            'title' => 'HiddenDraft',
            'status' => ProjectAssessmentStatus::Draft,
        ]);
        $teamA = $this->joinTeam($a, $published);
        $teamB = $this->joinTeam($b, $published);

        $this->actingAs($a)
            ->get(route('student.projects.show', $teamB))
            ->assertNotFound();

        $this->actingAs($a)
            ->get(route('student.projects.index', $offering))
            ->assertOk()
            ->assertSee('Visible')
            ->assertDontSee('HiddenDraft');

        $this->actingAs($a)
            ->post(route('student.projects.join', [$offering, $draft]))
            ->assertNotFound();

        $this->actingAs($outsider)
            ->get(route('student.projects.index', $offering))
            ->assertNotFound();

        $this->actingAs($outsider)
            ->post(route('student.projects.join', [$offering, $published]))
            ->assertForbidden();
    }

    #[Test]
    public function student_can_upload_and_delete_own_submission_file(): void
    {
        Storage::fake('local');
        $offering = $this->offering('WSP5');
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $project = $this->joinTeam($student, $assessment);
        $deliverable = $this->fileDeliverable($assessment);

        $this->actingAs($student)
            ->from(route('student.projects.show', $project))
            ->post(route('student.projects.submit', [$project, $deliverable]), [
                'files' => [UploadedFile::fake()->create('report.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect(route('student.projects.show', $project));

        $file = $project->submissions()->firstOrFail()->files()->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.projects.show', $project))
            ->assertOk()
            ->assertSee('report.pdf');

        $this->actingAs($student)
            ->from(route('student.projects.show', $project))
            ->delete(route('student.projects.files.destroy', [$project, $file]))
            ->assertRedirect(route('student.projects.show', $project));

        $this->assertSame(0, $project->fresh()->submissions()->firstOrFail()->files()->count());
    }

    #[Test]
    public function peer_eval_from_the_web_does_not_write_project_grades(): void
    {
        $offering = $this->offering('WSP6');
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

        $this->actingAs($a)
            ->get(route('student.projects.show', $project))
            ->assertOk()
            ->assertSee($b->first_name)
            ->assertSee(__('projects.peer_anonymous'));

        $this->actingAs($a)
            ->from(route('student.projects.show', $project))
            ->post(route('student.projects.peer.store', $project), [
                'ratee_id' => $b->id,
                'score' => 1,
                'comment' => 'low',
            ])
            ->assertRedirect(route('student.projects.show', $project));

        $after = DB::table('project_grades')->orderBy('id')->get()->toJson();
        $this->assertSame($before, $after);
        $this->assertSame(0, ProjectGrade::query()->where('score', 1)->count());

        $this->grading()->announce($instructor, $assessment);
        $this->assertSame(90.0, $this->grading()->announcedPercentForStudent($assessment, $a));
        $this->assertSame(0, ProjectGrade::query()->where('score', 1)->count());
    }

    #[Test]
    public function player_learn_and_hub_link_to_student_projects(): void
    {
        $offering = $this->offering('WSP7');
        $student = $this->studentOn($offering);
        $this->publishedAssessment($offering);
        $empty = $this->offering('WSP8');
        $this->enroll($student, $empty);

        $projectsUrl = route('student.projects.index', $offering);

        $this->actingAs($student)
            ->get(route('courses.player', $offering))
            ->assertOk()
            ->assertSee(__('projects.nav'))
            ->assertSee($projectsUrl, false);

        $this->actingAs($student)
            ->get(route('courses.player', $empty))
            ->assertOk()
            ->assertDontSee($projectsUrl, false);

        $this->actingAs($student)
            ->get(route('learn.offering', $offering))
            ->assertOk()
            ->assertSee(__('projects.nav'))
            ->assertSee($projectsUrl, false);

        $this->actingAs($student)
            ->get(route('hubs.learning'))
            ->assertOk()
            ->assertSee(__('hubs.projects'))
            ->assertSee(route('student.projects.mine'), false);

        $this->actingAs($student)
            ->get(route('student.projects.mine'))
            ->assertOk()
            ->assertSee(__('projects.mine_title'));
    }

    #[Test]
    public function staff_project_ui_is_unchanged_for_an_instructor(): void
    {
        $offering = $this->offering('WSP9');
        $instructor = $this->instructorOn($offering);
        $student = $this->studentOn($offering);
        $assessment = $this->publishedAssessment($offering);
        $team = $this->joinTeam($student, $assessment);

        $this->actingAs($instructor)
            ->get(route('teach.projects.index', $offering))
            ->assertOk()
            ->assertSee($assessment->title)
            ->assertSee(route('teach.projects.show', [$offering, $assessment]), false);

        $this->actingAs($instructor)
            ->get(route('teach.projects.show', [$offering, $assessment]))
            ->assertOk()
            ->assertSee($team->name)
            ->assertSee($student->first_name)
            ->assertDontSee(__('projects.join_any'));
    }
}
