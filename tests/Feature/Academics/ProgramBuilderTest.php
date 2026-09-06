<?php

namespace Tests\Feature\Academics;

use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgramBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function academic_admin_can_build_program_with_required_and_elective_courses(): void
    {
        $this->seed();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $scheme = GradingScheme::query()->first();

        $this->actingAs($aca)->post(route('admin.programs.store'), [
            'code' => 'theo',
            'name' => 'Theology Diploma',
            'type' => ProgramType::Diploma->value,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 6,
            'grading_scheme_id' => $scheme->id,
            'signatory_name' => 'Fr. John',
            'signatory_title' => 'Dean',
        ])->assertRedirect();

        $program = Program::query()->where('code', 'THEO')->first();
        $this->assertNotNull($program);
        $this->assertSame(6, $program->elective_credits_required);

        $required = Course::query()->create([
            'code' => 'TH101',
            'title' => 'Intro Theology',
            'credit_hours' => 3,
        ]);
        $elective = Course::query()->create([
            'code' => 'TH201',
            'title' => 'Patristics',
            'credit_hours' => 3,
        ]);

        $this->actingAs($aca)->post(route('admin.programs.attach-course', $program), [
            'course_id' => $required->id,
            'requirement' => RequirementType::Required->value,
            'year_level' => 1,
        ])->assertRedirect();

        $this->actingAs($aca)->post(route('admin.programs.attach-course', $program), [
            'course_id' => $elective->id,
            'requirement' => RequirementType::Elective->value,
            'year_level' => 2,
        ])->assertRedirect();

        $this->assertSame(2, $program->fresh()->programCourses()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'programs.create']);
    }

    #[Test]
    public function student_cannot_create_program(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->post(route('admin.programs.store'), [
            'code' => 'X',
            'name' => 'Nope',
            'type' => ProgramType::Certificate->value,
            'max_credits_per_semester' => 12,
            'max_courses_per_semester' => 4,
            'max_semesters_to_graduate' => 4,
        ])->assertForbidden();
    }

    #[Test]
    public function academic_admin_can_update_program_name_and_deactivate(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $program = Program::query()->create([
            'code' => 'EDIT1',
            'name' => 'Old Name',
            'type' => ProgramType::Certificate,
            'max_credits_per_semester' => 12,
            'max_courses_per_semester' => 4,
            'max_semesters_to_graduate' => 4,
            'active' => true,
        ]);

        $this->actingAs($aca)->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee(__('ui.edit'), false);

        $this->actingAs($aca)->get(route('admin.programs.edit', $program))
            ->assertOk()
            ->assertSee('Old Name');

        $this->actingAs($aca)->put(route('admin.programs.update', $program), [
            'name' => 'Corrected Name',
            'type' => ProgramType::Certificate->value,
            'max_credits_per_semester' => 12,
            'max_courses_per_semester' => 4,
            'max_semesters_to_graduate' => 4,
            'active' => 0,
        ])->assertRedirect(route('admin.programs.show', $program));

        $program->refresh();
        $this->assertSame('Corrected Name', $program->name);
        $this->assertFalse($program->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'programs.update']);
    }

    #[Test]
    public function student_cannot_update_program(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $program = Program::query()->create([
            'code' => 'NOPE',
            'name' => 'Locked',
            'type' => ProgramType::Certificate,
            'max_credits_per_semester' => 12,
            'max_courses_per_semester' => 4,
            'max_semesters_to_graduate' => 4,
            'active' => true,
        ]);

        $this->actingAs($student)->get(route('admin.programs.edit', $program))->assertForbidden();
        $this->actingAs($student)->put(route('admin.programs.update', $program), [
            'name' => 'Hacked',
            'type' => ProgramType::Certificate->value,
            'max_credits_per_semester' => 12,
            'max_courses_per_semester' => 4,
            'max_semesters_to_graduate' => 4,
            'active' => 0,
        ])->assertForbidden();

        $this->assertSame('Locked', $program->fresh()->name);
        $this->assertTrue($program->fresh()->active);
    }
}
