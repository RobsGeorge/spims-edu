<?php

namespace Tests\Feature\Portal;

use App\Enums\Currency;
use App\Enums\EnrollmentStatus;
use App\Enums\FormFieldType;
use App\Enums\InvoiceStatus;
use App\Enums\OfferingMode;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProgramType;
use App\Enums\RoleType;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\Invoice;
use App\Models\LiveSession;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalI2ResidualTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function finance_admin_can_view_reports_by_currency(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $open = Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Usd,
            'total_minor' => 5000,
            'status' => InvoiceStatus::Open,
            'due_date' => now()->addWeek(),
        ]);

        Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => $open->id,
            'currency' => Currency::Usd,
            'amount_minor' => 1200,
            'method' => PaymentMethod::ManualTransfer,
            'status' => PaymentStatus::Completed,
            'receipt_serial' => 'R-I2-1',
        ]);

        // Refresh amountDue awareness: open invoice still has remaining due.
        $this->actingAs($fin)->get(route('admin.finance.reports'))
            ->assertOk()
            ->assertSee(__('finance.reports_title'))
            ->assertSee(__('finance.outstanding_by_currency'))
            ->assertSee(__('finance.paid_revenue_by_currency'))
            ->assertSee('USD 38.00', false)
            ->assertSee('USD 12.00', false);
    }

    #[Test]
    public function observability_shows_queue_driver_and_failed_jobs(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('superadmin.observability.index'))
            ->assertOk()
            ->assertSee(__('superadmin.queue_connection'))
            ->assertSee(config('queue.default'))
            ->assertSee('failed_jobs', false)
            ->assertSee(__('superadmin.last_backup'));
    }

    #[Test]
    public function enrollment_flashes_schedule_conflict_warning(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);

        $courseA = Course::query()->create([
            'code' => 'I2A',
            'title' => 'Course A',
            'credit_hours' => 2,
            'default_price_usd' => 0,
            'default_price_egp' => 0,
            'is_standalone' => true,
            'active' => true,
        ]);
        $courseB = Course::query()->create([
            'code' => 'I2B',
            'title' => 'Course B',
            'credit_hours' => 2,
            'default_price_usd' => 0,
            'default_price_egp' => 0,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offeringA = CourseOffering::query()->create([
            'course_id' => $courseA->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $offeringB = CourseOffering::query()->create([
            'course_id' => $courseB->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offeringA->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $start = now()->addDay()->seconds(0);
        LiveSession::query()->create([
            'offering_id' => $offeringA->id,
            'title' => 'A live',
            'scheduled_start' => $start,
            'duration_minutes' => 60,
        ]);
        LiveSession::query()->create([
            'offering_id' => $offeringB->id,
            'title' => 'B live',
            'scheduled_start' => $start->copy()->addMinutes(30),
            'duration_minutes' => 60,
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offeringB->id,
        ])->assertRedirect()
            ->assertSessionHas('status', __('enrollment.registered'))
            ->assertSessionHas('warning', __('enrollment.schedule_conflict_warning'));
    }

    #[Test]
    public function applicant_can_upload_file_field_document(): void
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);

        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create(['is_reviewer' => true]);
        $student = User::factory()->withRole(RoleType::Student)->create();

        $program = Program::query()->create([
            'code' => 'I2DOC',
            'name' => 'Docs Program',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        $this->actingAs($adm)->post(route('admin.application-forms.store'), [
            'program_id' => $program->id,
            'name' => 'Doc Form',
            'fields' => [
                ['label' => 'ID Scan', 'type' => FormFieldType::File->value, 'required' => true],
            ],
        ])->assertRedirect();

        $form = ApplicationForm::query()->first();
        $this->actingAs($student)->get(route('applications.create', $form))->assertOk();
        $application = Application::query()->first();
        $fieldId = $form->fields()->first()->id;

        $file = UploadedFile::fake()->create('id-scan.pdf', 120, 'application/pdf');

        $this->actingAs($student)->post(route('applications.store', $application), [
            'files' => [$fieldId => $file],
            'submit' => '0',
        ])->assertRedirect(route('applications.index'));

        $application->refresh()->load('values');
        $value = $application->values->first();
        $this->assertNotNull($value?->file_url);
        $this->assertStringContainsString('application-docs/', (string) $value->file_url);
        Storage::disk('local')->assertExists($value->file_url);

        $this->assertTrue(
            AuditLog::query()->where('action', 'admissions.document_upload')->exists()
        );
    }
}
