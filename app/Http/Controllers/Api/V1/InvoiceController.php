<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Services\Finance\PaymentService;
use App\Support\Api\IdempotencyStore;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use App\Support\MoneyPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly StudentRecordGuard $guard,
        private readonly PaymentService $payments,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $page = Invoice::query()
            ->where('student_id', $request->user()->id)
            ->with(['lines', 'payments'])
            ->latest()
            ->paginate($perPage);

        $page->setCollection($page->getCollection()->map(fn (Invoice $invoice) => $this->payload($invoice)));

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->guard->ownRead($request->user(), $invoice->student_id);

        return response()->json(['data' => $this->payload($invoice->load(['lines', 'payments']))]);
    }

    public function checkout(Request $request, Invoice $invoice): JsonResponse
    {
        $this->guard->ownWrite($request->user(), $invoice->student_id);
        $data = $request->validate([
            'wallet_money' => 'nullable|integer|min:0',
            'wallet_points' => 'nullable|integer|min:0',
            'gateway' => 'nullable|string',
        ]);

        $payload = $this->idempotency->remember(
            $request->user(),
            'invoices.checkout:'.$invoice->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $invoice, $data) {
                $payment = $this->payments->checkout($request->user(), $invoice, [
                    'wallet_money' => (int) ($data['wallet_money'] ?? 0),
                    'wallet_points' => (int) ($data['wallet_points'] ?? 0),
                    'gateway' => $data['gateway'] ?? null,
                ]);

                return [
                    'id' => $payment->id,
                    'status' => $payment->status->value,
                    'method' => $payment->method->value,
                    'gateway_ref' => $payment->gateway_ref,
                    'receipt_serial' => $payment->receipt_serial,
                    'amount' => MoneyPayload::fromMinor($payment->amount_minor, $payment->currency),
                ];
            },
        );

        return response()->json(['data' => $payload]);
    }

    /** @return array<string, mixed> */
    private function payload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'status' => $invoice->status->value,
            'due_date' => StudentPayload::iso($invoice->due_date),
            'total' => MoneyPayload::fromMinor($invoice->total_minor, $invoice->currency),
            'amount_due' => MoneyPayload::fromMinor($invoice->amountDue(), $invoice->currency),
            'amount_paid' => MoneyPayload::fromMinor($invoice->amountPaid(), $invoice->currency),
            'lines' => $invoice->lines->map(fn (InvoiceLine $line) => [
                'id' => $line->id,
                'description' => $line->description,
                'amount' => MoneyPayload::fromMinor($line->amount_minor, $invoice->currency),
            ])->values(),
            'payments' => $invoice->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'status' => $payment->status->value,
                'method' => $payment->method->value,
                'amount' => MoneyPayload::fromMinor($payment->amount_minor, $payment->currency),
                'created_at' => StudentPayload::iso($payment->created_at),
            ])->values(),
        ];
    }
}
