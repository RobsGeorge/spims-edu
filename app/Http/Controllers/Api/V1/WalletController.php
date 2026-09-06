<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Currency;
use App\Enums\WalletKind;
use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\Finance\WalletService;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use App\Support\MoneyPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, WalletService $wallets): JsonResponse
    {
        $wallet = $wallets->ensureWallet($request->user());
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);

        $page = $wallet->transactions()->latest('created_at')->paginate($perPage);
        $page->setCollection($page->getCollection()->map(fn (WalletTransaction $tx) => [
            'id' => $tx->id,
            'direction' => $tx->direction->value,
            'kind' => $tx->kind->value,
            'reason' => $tx->reason->value,
            'amount' => MoneyPayload::fromMinor($tx->amount_minor, $tx->currency),
            'note' => $tx->note,
            'created_at' => StudentPayload::iso($tx->created_at),
        ]));

        $envelope = PaginatedEnvelope::from($page);
        $envelope['data'] = [
            'balances' => [
                'egp_money' => MoneyPayload::fromMinor($wallet->balance(Currency::Egp, WalletKind::Money), Currency::Egp),
                'usd_money' => MoneyPayload::fromMinor($wallet->balance(Currency::Usd, WalletKind::Money), Currency::Usd),
                'egp_points' => MoneyPayload::fromMinor($wallet->balance(Currency::Egp, WalletKind::Points), Currency::Egp),
                'usd_points' => MoneyPayload::fromMinor($wallet->balance(Currency::Usd, WalletKind::Points), Currency::Usd),
            ],
            'ledger' => $envelope['data'],
        ];

        return response()->json($envelope);
    }
}
