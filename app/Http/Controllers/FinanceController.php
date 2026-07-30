<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\PaymentService;
use App\Services\Finance\ReceiptPdfService;
use App\Services\Finance\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request, WalletService $wallets): View
    {
        $user = $request->user();
        $wallet = $wallets->ensureWallet($user);

        return view('finance.index', [
            'invoices' => Invoice::query()
                ->where('student_id', $user->id)
                ->with(['lines', 'payments'])
                ->latest()
                ->get(),
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()->latest('created_at')->limit(20)->get(),
        ]);
    }

    public function showInvoice(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->student_id === $request->user()->id || $request->user()->isSuperAdmin(), 403);

        return view('finance.invoice', [
            'invoice' => $invoice->load(['lines', 'payments', 'enrollment.offering.course']),
            'wallet' => app(WalletService::class)->ensureWallet($request->user()),
        ]);
    }

    public function checkout(Request $request, Invoice $invoice, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'wallet_money' => 'nullable|integer|min:0',
            'wallet_points' => 'nullable|integer|min:0',
            'gateway' => 'nullable|string',
        ]);

        $payments->checkout($request->user(), $invoice, [
            'wallet_money' => (int) ($data['wallet_money'] ?? 0),
            'wallet_points' => (int) ($data['wallet_points'] ?? 0),
            'gateway' => $data['gateway'] ?? null,
        ]);

        return redirect()->route('finance.index')->with('status', __('finance.payment_success'));
    }

    public function showReceipt(Request $request, Payment $payment, ReceiptPdfService $receipts): View
    {
        abort_unless(
            $payment->student_id === $request->user()->id || $request->user()->isSuperAdmin(),
            403
        );
        abort_unless(filled($payment->receipt_serial), 404);

        $receipts->ensure($payment);

        return view('finance.receipt', [
            'payment' => $payment->fresh()->load(['invoice']),
        ]);
    }
}
