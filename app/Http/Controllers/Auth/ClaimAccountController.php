<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * L5 — the landing page behind a legacy account-claim invitation link
 * (docs/legacy-data-import-plan.md §9). Its only job is to establish the same
 * session state the normal registration flow sets after AuthService::register(), so
 * the claimant then goes through the existing, unmodified auth.verify ->
 * auth.password.create flow exactly like any other new user. It creates no account,
 * sets no password, and issues no OTP — that already happened when the invite mail
 * was sent (ImportAccountClaimService::send()).
 *
 * The URL is Laravel-signed (the `signed` middleware rejects a tampered or expired
 * one before this action ever runs), so no additional guard is needed here.
 */
class ClaimAccountController extends Controller
{
    public function show(User $user): RedirectResponse
    {
        // Already claimed, or never eligible in the first place (e.g. suspended in
        // the meantime) — never hand out a working pending_user_id session for those.
        if ($user->status !== UserStatus::Pending) {
            return redirect()->route('auth.login')->with('status', __('import.claim_already_used'));
        }

        session(['pending_user_id' => $user->id]);

        return redirect()->route('auth.verify');
    }
}
