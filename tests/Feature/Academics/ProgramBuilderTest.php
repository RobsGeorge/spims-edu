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
}
