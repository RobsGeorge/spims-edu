<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
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
    public function index(Request $request, WalletService $wallets, ReceiptPdfService $receipts): View
    {
        $user = $request->user();
        $wallet = $wallets->ensureWallet($user);

        $invoices = Invoice::query()
            ->where('student_id', $user->id)
            ->with(['lines', 'payments.refunds'])
            ->latest()
            ->paginate(15, ['*'], 'invoices')
            ->withQueryString();

        foreach ($invoices as $invoice) {
            foreach ($invoice->payments as $payment) {
                if ($payment->status === PaymentStatus::Completed) {
                    $receipts->ensure($payment);
                }
            }
        }

        return view('finance.index', [
            'invoices' => $invoices,
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()
                ->latest('created_at')
                ->paginate(15, ['*'], 'ledger')
                ->withQueryString(),
        ]);
    }

    public function showInvoice(Request $request, Invoice $invoice, ReceiptPdfService $receipts): View
    {
        abort_unless($invoice->student_id === $request->user()->id || $request->user()->isSuperAdmin(), 403);

        $invoice->load(['lines', 'payments.refunds', 'enrollment.offering.course']);

        foreach ($invoice->payments as $payment) {
            if ($payment->status === PaymentStatus::Completed) {
                $receipts->ensure($payment);
            }
        }

        return view('finance.invoice', [
            'invoice' => $invoice->fresh(['lines', 'payments.refunds', 'enrollment.offering.course']),
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

    public function requestRefund(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->student_id === $request->user()->id, 403);

        $data = $request->validate([
            'amount_minor' => 'required|integer|min:1|max:'.$payment->amount_minor,
            'reason' => 'nullable|string|max:500',
            'as_points' => 'sometimes|boolean',
        ]);

        $payments->requestRefund(
            $request->user(),
            $payment,
            (int) $data['amount_minor'],
            $request->boolean('as_points'),
            $data['reason'] ?? null
        );

        return back()->with('status', __('finance.refund_requested'));
    }

    public function showReceipt(Request $request, Payment $payment, ReceiptPdfService $receipts): View
    {
        abort_unless(
            $payment->student_id === $request->user()->id || $request->user()->isSuperAdmin(),
            403
        );

        if ($payment->status === PaymentStatus::Completed) {
            $receipts->ensure($payment);
            $payment->refresh();
        }

        abort_unless(filled($payment->receipt_serial), 404);

        return view('finance.receipt', [
            'payment' => $payment->fresh()->load(['invoice']),
        ]);
    }
}
