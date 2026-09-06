<?php

namespace Tests\Feature\Admissions;

use App\Enums\ApplicationStatus;
use App\Enums\FormFieldType;
use App\Enums\ProgramType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\StudentProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdmissionsFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedProgramAndForm(User $adm): ApplicationForm
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $program = Program::query()->create([
            'code' => 'THEO',
            'name' => 'Theology',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        $this->actingAs($adm)->post(route('admin.application-forms.store'), [
            'program_id' => $program->id,
            'name' => 'THEO Apply',
            'fields' => [
                ['label' => 'Motivation', 'type' => FormFieldType::Textarea->value, 'required' => true],
                ['label' => 'Phone', 'type' => FormFieldType::Text->value, 'required' => false],
            ],
        ])->assertRedirect();

        return ApplicationForm::query()->first();
    }

    #[Test]
    public function applicant_submits_reviewer_accepts_and_matriculates(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create([
            'is_reviewer' => true,
        ]);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $form = $this->seedProgramAndForm($adm);

        $this->actingAs($student)->get(route('applications.create', $form))->assertOk();
        $application = Application::query()->first();

        $fieldId = $form->fields()->where('label', 'Motivation')->value('id');

        $this->actingAs($student)->post(route('applications.store', $application), [
            'answers' => [$fieldId => 'I want to study theology'],
            'submit' => '1',
        ])->assertRedirect(route('applications.index'));

        $application->refresh();
        $this->assertSame(ApplicationStatus::UnderReview, $application->status);
        $this->assertSame($adm->id, $application->reviewer_id);

        $this->actingAs($adm)->post(route('admin.applications.decide', $application), [
            'decision' => ApplicationStatus::Accepted->value,
            'decision_note' => 'Welcome',
        ])->assertRedirect();

        $this->assertSame(ApplicationStatus::Accepted, $application->fresh()->status);
        $this->assertDatabaseHas('student_programs', [
            'student_id' => $student->id,
            'program_id' => $form->program_id,
            'status' => StudentProgramStatus::Active->value,
        ]);
    }

    #[Test]
    public function round_robin_assigns_least_recent_reviewer(): void
    {
        $r1 = User::factory()->withRole(RoleType::AdministrativeAdmin)->create([
            'is_reviewer' => true,
            'last_reviewed_at' => now()->subDays(2),
        ]);
        $r2 = User::factory()->withRole(RoleType::AdministrativeAdmin)->create([
            'is_reviewer' => true,
            'last_reviewed_at' => now()->subDays(10),
        ]);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $form = $this->seedProgramAndForm($r1);

        $this->actingAs($student)->get(route('applications.create', $form));
        $application = Application::query()->first();
        $fieldId = $form->fields()->first()->id;

        $this->actingAs($student)->post(route('applications.store', $application), [
            'answers' => [$fieldId => 'Hello'],
            'submit' => '1',
        ]);

        $this->assertSame($r2->id, $application->fresh()->reviewer_id);
    }

    #[Test]
    public function administrative_admin_can_update_form_and_deactivate_fields(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $form = $this->seedProgramAndForm($adm);

        $this->actingAs($adm)->get(route('admin.application-forms.show', $form))
            ->assertOk()
            ->assertSee('THEO Apply')
            ->assertSee(__('admissions.edit_form'));

        $this->actingAs($adm)->put(route('admin.application-forms.update', $form), [
            'name' => 'THEO Apply Revised',
            'active' => 1,
        ])->assertRedirect(route('admin.application-forms.show', $form));

        $this->assertSame('THEO Apply Revised', $form->fresh()->name);

        $this->actingAs($adm)->post(route('admin.application-forms.fields.store', $form), [
            'label' => 'Parish',
            'type' => FormFieldType::Text->value,
            'required' => false,
        ])->assertRedirect();

        $field = $form->fields()->where('label', 'Parish')->first();
        $this->assertNotNull($field);
        $this->assertTrue($field->active);

        $this->actingAs($adm)->post(route('admin.application-forms.fields.deactivate', [$form, $field]))
            ->assertRedirect();

        $this->assertFalse($field->fresh()->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admissions.form_update']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admissions.form_deactivate_field']);
    }

    #[Test]
    public function student_cannot_update_application_form(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $form = $this->seedProgramAndForm($adm);

        $this->actingAs($student)->get(route('admin.application-forms.show', $form))->assertForbidden();
        $this->actingAs($student)->put(route('admin.application-forms.update', $form), [
            'name' => 'Hacked',
            'active' => 0,
        ])->assertForbidden();

        $this->assertSame('THEO Apply', $form->fresh()->name);
    }
}
