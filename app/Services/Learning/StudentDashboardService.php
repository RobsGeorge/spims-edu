<?php

namespace App\Services\Learning;

use App\Enums\Currency;
use App\Enums\EnrollmentStatus;
use App\Enums\WalletKind;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\Notification;
use App\Models\User;
use App\Services\Finance\WalletService;
use App\Support\Money;

class StudentDashboardService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly OfferingAccessService $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $enrollments = Enrollment::query()
            ->where('student_id', $user->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->with(['offering.course', 'offering.semester'])
            ->latest('enrolled_at')
            ->limit(12)
            ->get();

        $offeringIds = $this->access->enrolledOfferingIds($user);

        $nextLive = null;
        if ($offeringIds !== []) {
            $nextLive = LiveSession::query()
                ->whereIn('offering_id', $offeringIds)
                ->where('scheduled_start', '>=', now()->subMinutes(15))
                ->with('offering.course')
                ->orderBy('scheduled_start')
                ->first();
        }

        $dueAssessments = collect();
        if ($offeringIds !== []) {
            $dueAssessments = Assessment::query()
                ->whereIn('offering_id', $offeringIds)
                ->where('released', true)
                ->where(function ($q) {
                    $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('opens_at')->orWhere('opens_at', '<=', now());
                })
                ->with('offering.course')
                ->orderByRaw('closes_at is null, closes_at asc')
                ->limit(6)
                ->get();
        }

        $wallet = $this->wallets->ensureWallet($user);

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'IN_APP')
            ->latest('created_at')
            ->limit(5)
            ->get();

        return [
            'enrollments' => $enrollments,
            'next_live' => $nextLive,
            'due_assessments' => $dueAssessments,
            'wallet' => [
                'egp_money' => Money::fromMinor($wallet->balance(Currency::Egp, WalletKind::Money), Currency::Egp)->format(),
                'usd_money' => Money::fromMinor($wallet->balance(Currency::Usd, WalletKind::Money), Currency::Usd)->format(),
                'egp_points' => number_format($wallet->balance(Currency::Egp, WalletKind::Points) / 100, 0),
                'usd_points' => number_format($wallet->balance(Currency::Usd, WalletKind::Points) / 100, 0),
            ],
            'notifications' => $notifications,
            'unread_notifications' => Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', 'IN_APP')
                ->whereNull('read_at')
                ->count(),
        ];
    }
}
