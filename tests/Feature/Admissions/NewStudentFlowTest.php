<?php

namespace Tests\Feature\Admissions;

use App\Enums\ApplicationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\FormFieldType;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\Notification;
use App\Models\StudentProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\StudentApiFixtures;
use Tests\TestCase;

class NewStudentFlowTest extends TestCase
{
    use RefreshDatabase;
    use StudentApiFixtures;

    /**
     * Dummy values for every required application field type a new student can face.
     *
     * @return array{answers: array<string, mixed>, files: array<string, UploadedFile>}
     */
    private function dummyAnswersFor(ApplicationForm $form): array
    {
        $answers = [];
        $files = [];

        foreach ($form->fields()->where('active', true)->get() as $field) {
            if (! $field->required) {
                continue;
            }

            if ($field->type === FormFieldType::File) {
                $files[$field->id] = UploadedFile::fake()->create('id-scan.pdf', 80, 'application/pdf');

                continue;
            }

            $answers[$field->id] = $this->dummyValueFor($field);
        }

        return ['answers' => $answers, 'files' => $files];
    }

    private function dummyValueFor(ApplicationFormField $field): mixed
    {
        $options = is_array($field->options) ? array_values($field->options) : [];

        return match ($field->type) {
            FormFieldType::Textarea => 'I am new to SPIMS and want to begin theological study this year.',
            FormFieldType::Number => '1998',
            FormFieldType::Date => '2005-01-07',
            FormFieldType::Select => $options[0] ?? 'Pastoral',
            FormFieldType::Multiselect => $options === [] ? ['Bible'] : array_slice($options, 0, 2),
            FormFieldType::Checkbox => '1',
            default => 'Lydia Newcomer',
        };
    }

    /**
     * @return array{adm: User, form: ApplicationForm, program: Program, programOffering: CourseOffering, standaloneOffering: CourseOffering}
     */
    private function seedCatalogAndForm(): array
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);

        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create([
            'is_reviewer' => true,
        ]);

        $program = Program::query()->create([
            'code' => 'NEW-DIP',
            'name' => 'New Student Diploma',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        $this->actingAs($adm)->post(route('admin.application-forms.store'), [
            'program_id' => $program->id,
            'name' => 'New Student Application',
            'fields' => [
                ['label' => 'Full name', 'type' => FormFieldType::Text->value, 'required' => true],
                ['label' => 'Why do you want to join?', 'type' => FormFieldType::Textarea->value, 'required' => true],
                ['label' => 'Year of birth', 'type' => FormFieldType::Number->value, 'required' => true],
                ['label' => 'Baptism date', 'type' => FormFieldType::Date->value, 'required' => true],
                ['label' => 'Preferred track', 'type' => FormFieldType::Select->value, 'required' => true],
                ['label' => 'Areas of interest', 'type' => FormFieldType::Multiselect->value, 'required' => true],
                ['label' => 'I agree to the honor code', 'type' => FormFieldType::Checkbox->value, 'required' => true],
                ['label' => 'ID scan', 'type' => FormFieldType::File->value, 'required' => true],
                ['label' => 'Optional notes', 'type' => FormFieldType::Text->value, 'required' => false],
            ],
        ])->assertRedirect();

        $form = ApplicationForm::query()->where('program_id', $program->id)->firstOrFail();
        $form->fields()->where('label', 'Preferred track')->update([
            'options' => ['Pastoral', 'Academic', 'Liturgical'],
        ]);
        $form->fields()->where('label', 'Areas of interest')->update([
            'options' => ['Bible', 'Liturgy', 'History'],
        ]);
        $form->load('fields');

        $programCourse = Course::query()->create([
            'code' => 'THEO101',
            'title' => 'Introduction to Theology',
            'credit_hours' => 3,
            'default_price_usd' => 0,
            'default_price_egp' => 0,
            'is_free' => true,
            'is_standalone' => false,
            'active' => true,
        ]);
        ProgramCourse::query()->create([
            'program_id' => $program->id,
            'course_id' => $programCourse->id,
            'requirement' => RequirementType::Required,
            'year_level' => 1,
        ]);
        $programOffering = CourseOffering::query()->create([
            'course_id' => $programCourse->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

        $standalone = Course::query()->create([
            'code' => 'ORIENT1',
            'title' => 'Open Orientation',
            'credit_hours' => 1,
            'default_price_usd' => 0,
            'default_price_egp' => 0,
            'is_free' => true,
            'is_standalone' => true,
            'active' => true,
        ]);
        $standaloneOffering = CourseOffering::query()->create([
            'course_id' => $standalone->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

        auth()->logout();
        $this->flushSession();

        return compact('adm', 'form', 'program', 'programOffering', 'standaloneOffering');
    }

    private function registerNewStudent(string $email = 'lydia.newcomer@example.com'): User
    {
        if (auth()->check()) {
            auth()->logout();
            $this->flushSession();
        }

        $this->post(route('auth.register'), [
            'email' => $email,
            'first_name' => 'Lydia',
            'last_name' => 'Newcomer',
            'phone' => '+201001234567',
            'preferred_locale' => 'en',
        ])->assertRedirect(route('auth.verify'));

        $otp = session('dev_otp');
        $this->assertNotEmpty($otp);

        $this->post(route('auth.verify'), ['code' => $otp])->assertRedirect(route('auth.password.create'));

        $this->post(route('auth.password.create'), [
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($user->hasRole(RoleType::Student));

        return $user;
    }

    #[Test]
    public function new_student_registers_and_sees_empty_student_surfaces(): void
    {
        $student = $this->registerNewStudent();

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('learning.my_courses_empty'))
            ->assertSee(__('learning.apply_to_study'))
            ->assertSee(__('learning.browse_catalog'))
            ->assertSee(__('learning.next_live_empty'))
            ->assertSee(__('learning.due_empty'))
            ->assertSee(__('learning.notifications_empty'))
            ->assertSee(__('ui.nav_my_applications'))
            ->assertSee(__('ui.nav_enrollments'));

        $this->actingAs($student)->get(route('applications.index'))
            ->assertOk()
            ->assertSee(__('admissions.no_applications'))
            ->assertSee(__('admissions.no_applications_help'));

        $this->actingAs($student)->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee(__('enrollment.no_enrollments'))
            ->assertSee(__('enrollment.no_programs'))
            ->assertSee(__('enrollment.apply_first'))
            ->assertDontSee('name="offering_id"', false);

        $this->actingAs($student)->get(route('finance.index'))
            ->assertOk()
            ->assertSee(__('finance.my_finance'))
            ->assertSee(__('ui.empty'));

        $this->actingAs($student)->get(route('grades.index'))
            ->assertOk()
            ->assertSee(__('learning.grades_empty'));

        $this->actingAs($student)->get(route('events.index'))
            ->assertOk()
            ->assertSee(__('events.catalog_empty'));

        $this->assertSame(0, Application::query()->where('applicant_id', $student->id)->count());
        $this->assertSame(0, Enrollment::query()->where('student_id', $student->id)->count());
        $this->assertSame(0, StudentProgram::query()->where('student_id', $student->id)->count());
    }

    #[Test]
    public function new_student_cannot_submit_application_without_required_field_answers(): void
    {
        $fixture = $this->seedCatalogAndForm();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Lydia',
            'last_name' => 'Newcomer',
        ]);

        $this->actingAs($student)->get(route('applications.create', $fixture['form']))->assertOk();
        $application = Application::query()->where('applicant_id', $student->id)->firstOrFail();

        $this->actingAs($student)->post(route('applications.store', $application), [
            'submit' => '1',
        ])->assertSessionHasErrors();

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
        $this->assertSame(0, ApplicationFieldValue::query()->where('application_id', $application->id)->count());
    }

    #[Test]
    public function new_student_applies_with_dummy_required_fields_is_accepted_and_enrolls(): void
    {
        Storage::fake('local');
        $fixture = $this->seedCatalogAndForm();
        $student = $this->registerNewStudent();

        $this->actingAs($student)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('NEW-DIP');

        $this->actingAs($student)
            ->get(route('programs.catalog.show', 'NEW-DIP'))
            ->assertOk()
            ->assertSee(__('programs.apply_now'));

        $this->actingAs($student)->get(route('applications.create', $fixture['form']))
            ->assertOk()
            ->assertSee('Full name')
            ->assertSee('Why do you want to join?')
            ->assertSee('ID scan');

        $application = Application::query()->where('applicant_id', $student->id)->firstOrFail();
        $this->assertSame(ApplicationStatus::Draft, $application->status);

        $payload = $this->dummyAnswersFor($fixture['form']->fresh('fields'));

        $this->actingAs($student)->post(route('applications.store', $application), [
            'answers' => $payload['answers'],
            'files' => $payload['files'],
            'submit' => '0',
        ])->assertRedirect(route('applications.show', $application))
            ->assertSessionHas('status', __('admissions.application_saved'));

        $application->refresh();
        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame(
            $fixture['form']->fields()->where('required', true)->count(),
            $application->values()->count()
        );

        $this->actingAs($student)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee(__('admissions.continue_draft'))
            ->assertSee('Lydia Newcomer');

        $this->actingAs($student)->post(route('applications.store', $application), [
            'answers' => $payload['answers'],
            'files' => $payload['files'],
            'submit' => '1',
        ])->assertRedirect(route('applications.show', $application))
            ->assertSessionHas('status', __('admissions.application_submitted'));

        $application->refresh();
        $this->assertSame(ApplicationStatus::UnderReview, $application->status);
        $this->assertSame($fixture['adm']->id, $application->reviewer_id);

        $this->actingAs($student)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee(__('admissions.waiting_review'))
            ->assertDontSee(__('admissions.submit'));

        $this->actingAs($student)->get(route('applications.create', $fixture['form']))
            ->assertRedirect(route('applications.show', $application));

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.admissions_cue_review'));

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $student->id)
                ->where('type', 'admissions.submitted')
                ->exists()
        );

        $this->actingAs($student)->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee('ORIENT1')
            ->assertDontSee('THEO101');

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $fixture['programOffering']->id,
        ])->assertSessionHasErrors('enrollment');

        $this->assertSame(0, Enrollment::query()->where('student_id', $student->id)->count());

        $this->actingAs($fixture['adm'])->post(route('admin.applications.decide', $application), [
            'decision' => ApplicationStatus::Accepted->value,
            'decision_note' => 'Welcome, new student',
        ])->assertRedirect();

        $this->assertSame(ApplicationStatus::Accepted, $application->fresh()->status);
        $this->assertTrue(
            Notification::query()
                ->where('user_id', $student->id)
                ->where('type', 'admissions.decided')
                ->exists()
        );
        $studentProgram = StudentProgram::query()
            ->where('student_id', $student->id)
            ->where('program_id', $fixture['program']->id)
            ->first();
        $this->assertNotNull($studentProgram);
        $this->assertSame(StudentProgramStatus::Active, $studentProgram->status);

        $this->actingAs($student)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee(__('admissions.accepted_enroll_cue'))
            ->assertSee(__('enrollment.enroll_now'));

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.admissions_cue_accepted'))
            ->assertSee(__('enrollment.enroll_now'));

        $this->actingAs($student)->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee('THEO101')
            ->assertSee('ORIENT1');

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $fixture['programOffering']->id,
            'student_program_id' => $studentProgram->id,
        ])->assertRedirect()
            ->assertSessionHas('status', __('enrollment.registered'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'offering_id' => $fixture['programOffering']->id,
            'status' => EnrollmentStatus::Enrolled->value,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('THEO101')
            ->assertDontSee(__('learning.my_courses_empty'));

        $this->actingAs($student)->get(route('applications.index'))
            ->assertOk()
            ->assertSee('NEW-DIP')
            ->assertSee(ApplicationStatus::Accepted->label())
            ->assertSee(__('enrollment.enroll_now'));

        $this->actingAs($student)->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee('THEO101')
            ->assertSee(__('enrollment.degree_audit'));
    }

    #[Test]
    public function new_student_can_flag_interest_and_enroll_in_standalone_course_before_admission(): void
    {
        $fixture = $this->seedCatalogAndForm();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Lydia',
            'last_name' => 'Newcomer',
            'country_code' => 'US',
        ]);
        $standalone = $fixture['standaloneOffering']->course;

        $this->actingAs($student)
            ->get(route('catalog.index', ['tab' => 'standalone']))
            ->assertOk()
            ->assertSee('ORIENT1');

        $this->actingAs($student)
            ->post(route('catalog.interest', $standalone))
            ->assertRedirect();

        $this->assertDatabaseHas('course_interest_flags', [
            'student_id' => $student->id,
            'course_id' => $standalone->id,
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $fixture['standaloneOffering']->id,
        ])->assertRedirect()
            ->assertSessionHas('status', __('enrollment.registered'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'offering_id' => $fixture['standaloneOffering']->id,
            'status' => EnrollmentStatus::Enrolled->value,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ORIENT1');

        $this->actingAs($student)->get(route('learn.offering', $fixture['standaloneOffering']))
            ->assertOk();
    }

    #[Test]
    public function new_student_api_applies_with_dummy_required_fields(): void
    {
        Storage::fake('local');
        $fixture = $this->seedCatalogAndForm();
        $student = $this->student([
            'first_name' => 'Lydia',
            'last_name' => 'Newcomer',
        ]);

        $this->asApi($student)
            ->getJson(route('api.v1.catalog.index'))
            ->assertOk();

        $this->asApi($student)
            ->getJson(route('api.v1.applications.index'))
            ->assertOk()
            ->assertJsonPath('data', []);

        $form = $fixture['form']->fresh('fields');
        $shown = $this->asApi($student)
            ->getJson(route('api.v1.application-forms.show', $form))
            ->assertOk()
            ->json('data');

        $this->assertSame('New Student Application', $shown['name']);
        $required = collect($shown['fields'])->where('required', true);
        $this->assertGreaterThanOrEqual(8, $required->count());

        $payload = $this->dummyAnswersFor($form);

        $applicationId = $this->asApi($student)
            ->post(route('api.v1.applications.store'), [
                'form_id' => $form->id,
                'answers' => $payload['answers'],
                'files' => $payload['files'],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asApi($student)
            ->postJson(route('api.v1.applications.submit', $applicationId), [], [
                'Idempotency-Key' => 'new-student-apply-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::UnderReview->value);

        $application = Application::query()->findOrFail($applicationId);
        $this->assertSame($student->id, $application->applicant_id);
        $this->assertSame(
            $form->fields()->where('required', true)->count(),
            $application->values()->count()
        );
        $this->assertTrue(
            Notification::query()
                ->where('user_id', $student->id)
                ->where('type', 'admissions.submitted')
                ->exists()
        );
    }

    #[Test]
    public function new_student_can_save_a_partial_draft_then_must_complete_required_fields_to_submit(): void
    {
        $fixture = $this->seedCatalogAndForm();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Lydia',
            'last_name' => 'Newcomer',
        ]);

        $this->actingAs($student)->get(route('applications.create', $fixture['form']))
            ->assertOk()
            ->assertSee('name="submit" value="0"', false)
            ->assertSee('formnovalidate', false)
            ->assertSee('<select', false);

        $application = Application::query()->where('applicant_id', $student->id)->firstOrFail();
        $nameFieldId = $fixture['form']->fields()->where('label', 'Full name')->value('id');

        $this->actingAs($student)->post(route('applications.store', $application), [
            'answers' => [$nameFieldId => 'Lydia Newcomer'],
            'submit' => '0',
        ])->assertRedirect(route('applications.show', $application))
            ->assertSessionHas('status', __('admissions.application_saved'));

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
        $this->assertSame(1, $application->values()->count());

        $this->actingAs($student)->post(route('applications.store', $application), [
            'answers' => [$nameFieldId => 'Lydia Newcomer'],
            'submit' => '1',
        ])->assertSessionHasErrors();

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }

    #[Test]
    public function other_student_cannot_view_someone_elses_application(): void
    {
        $fixture = $this->seedCatalogAndForm();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $other = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('applications.create', $fixture['form']))->assertOk();
        $application = Application::query()->where('applicant_id', $student->id)->firstOrFail();

        $this->actingAs($other)->get(route('applications.show', $application))->assertForbidden();
    }

    #[Test]
    public function catalog_apply_names_the_program(): void
    {
        $fixture = $this->seedCatalogAndForm();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee(__('catalog.apply_to', ['program' => 'NEW-DIP']));
    }

    #[Test]
    public function new_student_flow_copy_exists_in_all_locales(): void
    {
        $keys = [
            'admissions.application_submitted',
            'admissions.waiting_review',
            'admissions.accepted_enroll_cue',
            'admissions.continue_draft',
            'admissions.view_application',
            'admissions.notify_submitted_title',
            'admissions.notify_decided_body',
            'admissions.select_placeholder',
            'catalog.apply_to',
            'enrollment.apply_first',
            'enrollment.enroll_now',
            'learning.apply_to_study',
            'dashboard.admissions_cue_review',
            'dashboard.admissions_cue_accepted',
        ];

        foreach (['en', 'ar', 'fr'] as $locale) {
            foreach ($keys as $key) {
                $this->assertNotSame(
                    $key,
                    __($key, ['program' => 'NEW-DIP', 'status' => 'Accepted'], $locale),
                    "Missing {$key} in {$locale}"
                );
            }
        }
    }
}
