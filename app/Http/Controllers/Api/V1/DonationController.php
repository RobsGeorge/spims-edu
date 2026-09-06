<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Currency;
use App\Enums\WalletKind;
use App\Http\Controllers\Controller;
use App\Services\Finance\DonationService;
use App\Support\Api\IdempotencyStore;
use App\Support\MoneyPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DonationController extends Controller
{
    public function store(
        Request $request,
        DonationService $donations,
        IdempotencyStore $idempotency,
    ): JsonResponse {
        $data = $request->validate([
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount_minor' => 'required|integer|min:1',
            'designation' => 'nullable|string|max:255',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'donations.store',
            $request->header('Idempotency-Key'),
            function () use ($request, $donations, $data) {
                $donation = $donations->donate(
                    $request->user(),
                    Currency::from($data['currency']),
                    (int) $data['amount_minor'],
                    WalletKind::Money,
                    $data['designation'] ?? null
                );

                return [
                    'id' => $donation->id,
                    'amount' => MoneyPayload::fromMinor($donation->amount_minor, $donation->currency),
                    'designation' => $donation->designation,
                    'payment_id' => $donation->payment_id,
                ];
            },
        );

        return response()->json(['data' => $payload], 201);
    }
}
