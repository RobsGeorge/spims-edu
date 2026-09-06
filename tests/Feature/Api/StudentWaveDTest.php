<?php

namespace Tests\Feature\Api;

use App\Enums\Currency;
use App\Enums\FormFieldType;
use App\Enums\InvoiceStatus;
use App\Enums\ProgramType;
use App\Enums\RoleType;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\AuditLog;
use App\Models\Donation;
use App\Models\GradingScheme;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentWaveDTest extends TestCase
{
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function catalog_is_public_and_course_detail_includes_money_objects(): void
    {
        $offering = $this->offering('CAT1', courseAttrs: [
            'default_price_usd' => 5000,
            'default_price_egp' => 150000,
            'is_free' => false,
        ]);

        $this->getJson(route('api.v1.catalog.index'))
            ->assertOk();

        $detail = $this->getJson(route('api.v1.catalog.courses.show', $offering->course))
            ->assertOk()
            ->json('data');

        $this->assertSame('CAT1', $detail['code']);
        $price = $detail['offerings'][0]['price'];
        $this->assertArrayHasKey('minor_units', $price);
        $this->assertArrayHasKey('currency', $price);
        $this->assertArrayHasKey('formatted', $price);
        $this->assertIsInt($price['minor_units']);
    }

    #[Test]
    public function student_can_apply_and_submit_an_application(): void
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create(['is_reviewer' => true]);
        $student = $this->student();
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
            ],
        ])->assertRedirect();
        $form = ApplicationForm::query()->first();
        $fieldId = $form->fields()->value('id');

        $this->asApi($student)
            ->getJson(route('api.v1.application-forms.show', $form))
            ->assertOk()
            ->assertJsonPath('data.fields.0.label', 'Motivation');

        $applicationId = $this->asApi($student)
            ->postJson(route('api.v1.applications.store'), [
                'form_id' => $form->id,
                'answers' => [$fieldId => 'I want to study'],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asApi($student)
            ->postJson(route('api.v1.applications.submit', $applicationId), [], [
                'Idempotency-Key' => 'apply-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'UNDER_REVIEW');

        $this->asApi($student)
            ->postJson(route('api.v1.applications.submit', $applicationId), [], [
                'Idempotency-Key' => 'apply-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'UNDER_REVIEW');

        $this->assertSame('UNDER_REVIEW', Application::query()->find($applicationId)->status->value);
        $this->assertSame(1, AuditLog::query()->where('action', 'admissions.submit')->count());
    }

    #[Test]
    public function enroll_returns_409_on_financial_hold(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $student = $this->student(['country_code' => 'US']);
        $first = $this->offering('PAY1', courseAttrs: [
            'default_price_usd' => 5000,
            'is_free' => false,
        ]);
        $second = $this->offering('PAY2', courseAttrs: [
            'default_price_usd' => 6000,
            'is_free' => false,
        ]);
        $token = $this->apiToken($student);

        $this->withToken($token)
            ->postJson(route('api.v1.enrollments.store'), ['offering_id' => $first->id])
            ->assertCreated();

        $this->assertNotNull(Invoice::query()->where('student_id', $student->id)->first());

        $this->withToken($token)
            ->postJson(route('api.v1.enrollments.store'), ['offering_id' => $second->id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    #[Test]
    public function invoice_money_shape_checkout_wallet_donation_and_foreign_invoice_is_404(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $student = $this->student(['country_code' => 'US']);
        $peer = $this->student(['country_code' => 'US']);
        $offering = $this->offering('INV1', courseAttrs: [
            'default_price_usd' => 5000,
            'is_free' => false,
        ]);
        $this->asApi($student)
            ->postJson(route('api.v1.enrollments.store'), ['offering_id' => $offering->id])
            ->assertCreated();

        $invoice = Invoice::query()->where('student_id', $student->id)->first();
        $this->assertSame(InvoiceStatus::Open, $invoice->status);

        $shown = $this->asApi($student)
            ->getJson(route('api.v1.invoices.show', $invoice))
            ->assertOk()
            ->json('data.total');
        $this->assertSame(5000, $shown['minor_units']);
        $this->assertSame('USD', $shown['currency']);
        $this->assertArrayHasKey('formatted', $shown);

        $this->asApi($peer)
            ->getJson(route('api.v1.invoices.show', $invoice))
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->asApi($student)
            ->postJson(route('api.v1.invoices.checkout', $invoice), [
                'wallet_money' => 0,
                'wallet_points' => 0,
                'gateway' => 'PAYPAL',
            ], ['Idempotency-Key' => 'pay-1'])
            ->assertOk()
            ->assertJsonPath('data.amount.minor_units', 5000);

        $replay = $this->asApi($student)
            ->postJson(route('api.v1.invoices.checkout', $invoice), [
                'wallet_money' => 0,
                'wallet_points' => 0,
                'gateway' => 'PAYPAL',
            ], ['Idempotency-Key' => 'pay-1'])
            ->assertOk();
        $this->assertSame(
            Payment::query()->where('invoice_id', $invoice->id)->value('id'),
            $replay->json('data.id')
        );
        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->count());

        $wallet = $this->asApi($student)
            ->getJson(route('api.v1.wallet'))
            ->assertOk()
            ->json('data.balances.usd_money');
        $this->assertArrayHasKey('minor_units', $wallet);
        $this->assertSame('USD', $wallet['currency']);

        $this->asApi($student)
            ->postJson(route('api.v1.donations.store'), [
                'currency' => Currency::Usd->value,
                'amount_minor' => 2500,
                'designation' => 'Chapel',
            ], ['Idempotency-Key' => 'donate-1'])
            ->assertCreated()
            ->assertJsonPath('data.amount.minor_units', 2500)
            ->assertJsonPath('data.amount.currency', 'USD');

        $this->asApi($student)
            ->postJson(route('api.v1.donations.store'), [
                'currency' => Currency::Usd->value,
                'amount_minor' => 2500,
                'designation' => 'Chapel',
            ], ['Idempotency-Key' => 'donate-1'])
            ->assertCreated();

        $this->assertSame(1, Donation::query()->where('user_id', $student->id)->count());
    }

    #[Test]
    public function catalog_interest_requires_auth(): void
    {
        $offering = $this->offering('INT1');

        $this->postJson(route('api.v1.catalog.courses.interest', $offering->course))
            ->assertUnauthorized();

        $this->withToken($this->apiToken($this->student()))
            ->postJson(route('api.v1.catalog.courses.interest', $offering->course))
            ->assertCreated();
    }
}
