<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\WalletKind;
use App\Services\Finance\DonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DonationController extends Controller
{
    public function create(): View
    {
        return view('finance.donate');
    }

    public function store(Request $request, DonationService $donations): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount_minor' => 'required|integer|min:1',
            'designation' => 'nullable|string|max:255',
        ]);

        $donations->donate(
            $request->user(),
            Currency::from($data['currency']),
            (int) $data['amount_minor'],
            WalletKind::Money,
            $data['designation'] ?? null
        );

        return redirect()->route('finance.index')->with('status', __('finance.donation_thanks'));
    }
}
