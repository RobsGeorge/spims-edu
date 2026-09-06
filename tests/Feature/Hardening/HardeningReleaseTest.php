<?php

namespace Tests\Feature\Hardening;

use App\Enums\InvoiceStatus;
use App\Enums\OfferingMode;
use App\Enums\OtpPurpose;
use App\Enums\PaymentStatus;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Support\ProductionSafeDefaults;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HardeningReleaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function health_endpoint_reports_ok_when_database_is_up(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', true);
    }

    #[Test]
    public function security_headers_are_present_on_home(): void
    {
        $this->seed();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function seed_creates_super_admin_and_sample_curriculum(): void
    {
        $this->seed();

        $this->assertNotNull(
            User::query()->where('email', env('SUPERADMIN_EMAIL'))->first()
        );
        $this->assertNotNull(Program::query()->where('code', 'DEMO-DIP')->first());
        $this->assertNotNull(Course::query()->where('code', 'DEMO101')->first());
        $this->assertTrue(CourseOffering::query()->exists());
    }

    #[Test]
    public function login_is_rate_limited(): void
    {
        $this->seed();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('auth.login'), [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    #[Test]
    public function backup_command_writes_sqlite_marker_in_memory(): void
    {
        $dir = storage_path('app/backups-test-'.uniqid());
        File::ensureDirectoryExists($dir);

        $this->artisan('spims:backup-database', [
            '--path' => $dir,
            '--keep' => 14,
        ])->assertSuccessful();

        $files = File::files($dir);
        $this->assertNotEmpty($files);

        File::deleteDirectory($dir);
    }

    #[Test]
    public function production_without_mock_flag_does_not_auto_complete_checkout(): void
    {
        $this->assertFalse(ProductionSafeDefaults::paymentsMockAutoComplete(null, 'production'));
        $this->assertFalse(ProductionSafeDefaults::paymentsMockAutoComplete(null, 'staging'));
        $this->assertTrue(ProductionSafeDefaults::paymentsMockAutoComplete(null, 'local'));
        $this->assertTrue(ProductionSafeDefaults::paymentsMockAutoComplete(null, 'testing'));

        $previousEnv = $this->app['env'];
        $previousMock = config('services.payments.mock_auto_complete');

        try {
            $this->app['env'] = 'production';
            config([
                'app.env' => 'production',
                'services.payments.mock_auto_complete' => ProductionSafeDefaults::paymentsMockAutoComplete(null, 'production'),
            ]);

            $this->assertFalse(config('services.payments.mock_auto_complete'));

            $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);
            $offering = $this->pricedOffering();

            $this->actingAs($student)->post(route('enrollments.store'), [
                'offering_id' => $offering->id,
            ])->assertRedirect();

            $invoice = Invoice::query()->where('student_id', $student->id)->first();
            $this->assertNotNull($invoice);

            $this->actingAs($student)->post(route('finance.checkout', $invoice), [
                'wallet_money' => 0,
                'wallet_points' => 0,
                'gateway' => 'PAYPAL',
            ])->assertRedirect(route('finance.index'));

            $payment = Payment::query()->first();
            $this->assertNotNull($payment);
            $this->assertSame(PaymentStatus::Pending, $payment->status);
            $this->assertNull($payment->receipt_serial);
            $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
        } finally {
            $this->app['env'] = $previousEnv;
            config([
                'app.env' => $previousEnv,
                'services.payments.mock_auto_complete' => $previousMock,
            ]);
        }
    }

    #[Test]
    public function production_refuses_webhooks_signed_with_test_default_secrets(): void
    {
        $previousEnv = $this->app['env'];

        try {
            $this->app['env'] = 'production';
            config(['app.env' => 'production']);

            $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);
            $pending = Payment::query()->create([
                'student_id' => $student->id,
                'invoice_id' => null,
                'currency' => \App\Enums\Currency::Usd,
                'amount_minor' => 100,
                'method' => \App\Enums\PaymentMethod::Paypal,
                'status' => PaymentStatus::Pending,
                'gateway_ref' => 'PAYPAL-PROD-GUARD',
            ]);

            $payload = ['id' => 'evt-prod-guard'];
            $signature = hash_hmac('sha256', json_encode($payload), 'paypal-test');

            $this->postJson(route('api.webhooks.payments'), [
                'method' => 'PAYPAL',
                'gateway_ref' => $pending->gateway_ref,
                'signature' => $signature,
                'payload' => $payload,
            ])->assertStatus(503);

            $this->assertSame(PaymentStatus::Pending, $pending->fresh()->status);

            $body = json_encode(['event' => 'endpoint.url_validation', 'payload' => ['plainToken' => 'abc']]);
            $ts = (string) time();
            $zoomSig = 'v0='.hash_hmac('sha256', 'v0:'.$ts.':'.$body, 'zoom-test');

            $this->call(
                'POST',
                route('api.webhooks.zoom'),
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_ZM_SIGNATURE' => $zoomSig,
                    'HTTP_X_ZM_REQUEST_TIMESTAMP' => $ts,
                ],
                $body
            )->assertStatus(503);
        } finally {
            $this->app['env'] = $previousEnv;
            config(['app.env' => $previousEnv]);
        }
    }

    #[Test]
    public function otp_notice_omits_the_digits(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create();

        Log::fake();

        $plain = app(OtpService::class)->issue($user, OtpPurpose::EmailVerification);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $plain);

        Log::assertLogged(function ($log) use ($user, $plain) {
            if (! str_contains($log->message, 'SPIMS OTP issued')) {
                return false;
            }

            $this->assertSame('notice', $log->level);
            $this->assertSame($user->id, $log->context['user_id'] ?? null);
            $this->assertSame(OtpPurpose::EmailVerification->value, $log->context['purpose'] ?? null);
            $this->assertArrayNotHasKey('code', $log->context);
            $this->assertStringNotContainsString($plain, $log->message);
            $this->assertStringNotContainsString($plain, json_encode($log->context));

            return true;
        });
    }

    #[Test]
    public function authorization_exception_is_not_reported_and_stays_403(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $this->assertFalse($handler->shouldReport(new AuthorizationException(__('auth.forbidden'))));

        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->get(route('admin.finance.index'))
            ->assertForbidden();
    }

    private function pricedOffering(): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'HARD1',
            'title' => 'Hardening Course',
            'credit_hours' => 3,
            'default_price_usd' => 5000,
            'default_price_egp' => 150000,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
    }
}
