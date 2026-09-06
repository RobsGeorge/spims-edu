<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Currency;
use App\Enums\WalletKind;
use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Services\Finance\WalletService;
use App\Services\Learning\StudentDashboardService;
use App\Support\Api\StudentPayload;
use App\Support\MoneyPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(
        Request $request,
        StudentDashboardService $dashboard,
        WalletService $wallets,
    ): JsonResponse {
        $user = $request->user();
        $payload = $dashboard->build($user);
        $wallet = $wallets->ensureWallet($user);

        return response()->json([
            'data' => [
                'offerings' => $payload['enrollments']->map(fn ($enrollment) => [
                    'id' => $enrollment->offering_id,
                    'enrollment_id' => $enrollment->id,
                    'course_code' => $enrollment->offering?->course?->code,
                    'course_title' => $enrollment->offering?->course?->title,
                    'progress_percent' => $enrollment->progress_percent,
                    'status' => $enrollment->status->value,
                ])->values(),
                'next_live' => $this->live($payload['next_live']),
                'due_items' => $payload['due_assessments']->map(fn ($assessment) => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'offering_id' => $assessment->offering_id,
                    'closes_at' => StudentPayload::iso($assessment->closes_at),
                ])->values(),
                'wallet' => [
                    'egp_money' => MoneyPayload::fromMinor($wallet->balance(Currency::Egp, WalletKind::Money), Currency::Egp),
                    'usd_money' => MoneyPayload::fromMinor($wallet->balance(Currency::Usd, WalletKind::Money), Currency::Usd),
                    'egp_points' => MoneyPayload::fromMinor($wallet->balance(Currency::Egp, WalletKind::Points), Currency::Egp),
                    'usd_points' => MoneyPayload::fromMinor($wallet->balance(Currency::Usd, WalletKind::Points), Currency::Usd),
                ],
                'unread_count' => $payload['unread_notifications'],
            ],
        ]);
    }

    private function live(?LiveSession $session): ?array
    {
        if ($session === null) {
            return null;
        }

        return [
            'id' => $session->id,
            'title' => $session->title,
            'offering_id' => $session->offering_id,
            'scheduled_start' => StudentPayload::iso($session->scheduled_start),
        ];
    }
}
