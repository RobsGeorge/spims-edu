<?php

namespace Tests\Feature\Portal;

use App\Enums\AssessmentMode;
use App\Enums\Currency;
use App\Enums\EnrollmentStatus;
use App\Enums\LedgerReason;
use App\Enums\OfferingMode;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Enums\WalletKind;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\QuestionBankService;
use App\Services\Finance\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalD6PolishTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function finance_index_shows_balance_cards_for_student_with_wallet(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);

        app(WalletService::class)->credit(
            $student,
            Currency::Usd,
            WalletKind::Money,
            2500,
            LedgerReason::Topup,
            $fin
        );

        $this->actingAs($student)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('wallet-balance-card', false)
            ->assertSee(__('learning.usd_money'))
            ->assertSee(__('learning.egp_money'))
            ->assertSee(__('learning.usd_points'))
            ->assertSee(__('learning.egp_points'))
            ->assertSee('USD 25.00');
    }

    #[Test]
    public function exam_runner_shows_progress_rail_and_localized_nav(): void
    {
        $ins = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'D6EX',
            'title' => 'D6 Exam Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'is_free' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $bank = app(QuestionBankService::class)->createBank($ins, $course, 'D6 Bank');
        $q = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::McqSingle->value,
            'prompt' => 'What is 1+1?',
            'points' => 10,
            'options' => [
                ['text' => '1', 'is_correct' => false],
                ['text' => '2', 'is_correct' => true],
            ],
        ]);

        $assessment = app(AssessmentService::class)->create($ins, $offering, [
            'title' => 'D6 Quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 20,
            'max_points' => 10,
            'one_at_a_time' => true,
        ]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $q);

        $attempt = app(AttemptService::class)->start($student, $assessment);

        $this->actingAs($student)
            ->get(route('assessments.runner', $attempt))
            ->assertOk()
            ->assertSee('exam-progress-rail', false)
            ->assertSee('examSubmitModal', false)
            ->assertSee(__('assessment.progress'))
            ->assertSee(__('assessment.prev'))
            ->assertSee(__('assessment.next'))
            ->assertSee(__('assessment.submit_confirm_title'));
    }

    #[Test]
    public function custom_404_page_renders_sacred_copy(): void
    {
        $this->get('/this-route-definitely-does-not-exist-d6')
            ->assertNotFound()
            ->assertSee('spims-error-page', false)
            ->assertSee(__('ui.error_404_title'))
            ->assertSee(__('ui.go_home'));
    }

    #[Test]
    public function student_can_view_receipt_when_serial_exists(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);
        $course = Course::query()->create([
            'code' => 'D6FIN',
            'title' => 'Receipt Course',
            'credit_hours' => 3,
            'default_price_usd' => 5000,
            'default_price_egp' => 150000,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);
        $invoice = Invoice::query()->create([
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'currency' => Currency::Usd,
            'total_minor' => 5000,
            'status' => 'PAID',
        ]);
        $payment = Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'currency' => Currency::Usd,
            'amount_minor' => 5000,
            'method' => PaymentMethod::Paypal,
            'status' => PaymentStatus::Completed,
            'receipt_serial' => 'SPIMS-2026-00001',
        ]);

        $this->actingAs($student)
            ->get(route('finance.receipts.show', $payment))
            ->assertOk()
            ->assertSee('SPIMS-2026-00001')
            ->assertSee(__('finance.receipt_title'))
            ->assertSee('USD 50.00');
    }
}
