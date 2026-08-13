<?php

namespace Tests\Feature\Integrations;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Finance\GatewayRouter;
use App\Services\Finance\ReceiptPdfService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationsI1Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function upload_endpoint_stores_file_for_authenticated_user(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $user = User::factory()->withRole(RoleType::Student)->create();
        $file = UploadedFile::fake()->create('essay.pdf', 120, 'application/pdf');

        $response = $this->actingAs($user)->postJson(route('api.uploads.store'), [
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['path', 'url']);

        $path = $response->json('path');
        $this->assertIsString($path);
        $this->assertStringStartsWith('uploads/'.$user->id.'/', $path);
        Storage::disk('local')->assertExists($path);
    }

    #[Test]
    public function ai_client_returns_null_without_gemini_key(): void
    {
        config(['services.gemini.key' => null]);

        $client = app(AiClient::class);

        $this->assertNull($client->translate('Hello', 'en', 'ar'));
        $this->assertNull($client->suggestEssayScore('Score this essay: liturgy'));
    }

    #[Test]
    public function receipt_pdf_service_writes_file_for_payment(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $student = User::factory()->withRole(RoleType::Student)->create([
            'preferred_locale' => 'en',
            'country_code' => 'US',
        ]);

        $invoice = Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Usd,
            'total_minor' => 5000,
            'status' => InvoiceStatus::Open,
        ]);

        $payment = Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'currency' => Currency::Usd,
            'amount_minor' => 5000,
            'method' => PaymentMethod::Paypal,
            'status' => PaymentStatus::Completed,
            'receipt_serial' => 'SPIMS-2026-00099',
            'receipt_url' => null,
        ]);

        $path = app(ReceiptPdfService::class)->generate($payment);

        $this->assertSame('receipts/'.$payment->id.'.html', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertStringContainsString('SPIMS-2026-00099', Storage::disk('local')->get($path));
        $this->assertSame($path, $payment->fresh()->receipt_url);
        $this->assertTrue(app(ObjectStorageService::class)->exists($path));
    }

    #[Test]
    public function gateway_router_charge_returns_id_when_mock_true(): void
    {
        config(['services.payments.mock_auto_complete' => true]);

        $ref = app(GatewayRouter::class)->charge(
            PaymentMethod::Paypal,
            2500,
            Currency::Usd,
            '01TESTPAYMENT00000000000000'
        );

        $this->assertIsString($ref);
        $this->assertNotSame('', $ref);
        $this->assertStringStartsWith('PAYPAL-', $ref);
    }
}
