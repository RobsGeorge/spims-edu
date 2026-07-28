<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Services\Finance\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'gateway_ref' => 'required|string',
            'signature' => 'required|string',
            'payload' => 'required|array',
        ]);

        $payment = $payments->handleWebhook(
            PaymentMethod::from($data['method']),
            $data['gateway_ref'],
            $data['signature'],
            $data['payload']
        );

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status->value,
            'receipt_serial' => $payment->receipt_serial,
        ]);
    }
}
