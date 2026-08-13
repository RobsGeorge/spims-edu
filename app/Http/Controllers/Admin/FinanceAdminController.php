<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleType;
use App\Enums\WalletKind;
use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\DonationService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\WalletService;
use App\Support\AuthorizeService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceAdminController extends Controller
{
    public function index(): View
    {
        $students = User::query()
            ->whereHas('roles', fn ($q) => $q->where('role', RoleType::Student->value))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('admin.finance.index', [
            'students' => $students,
            'invoices' => Invoice::query()->with('student')->latest()->limit(50)->get(),
            'pendingManual' => Payment::query()
                ->where('status', \App\Enums\PaymentStatus::PendingVerification)
                ->with(['student', 'invoice'])
                ->latest('created_at')
                ->get(),
            'refunds' => Refund::query()->with('student')->latest('created_at')->limit(30)->get(),
        ]);
    }

    public function storeInvoice(Request $request, InvoiceService $invoices): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'currency' => ['required', Rule::enum(Currency::class)],
            'total_minor' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
        ]);

        $invoices->createManual(
            $request->user(),
            User::query()->findOrFail($data['student_id']),
            Currency::from($data['currency']),
            (int) $data['total_minor'],
            $data['description']
        );

        return back()->with('status', __('finance.invoice_created'));
    }

    public function recordManual(Request $request, Invoice $invoice, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in([
                PaymentMethod::ManualCash->value,
                PaymentMethod::ManualTransfer->value,
                PaymentMethod::ManualCheque->value,
            ])],
            'amount_minor' => 'required|integer|min:1',
            'proof_url' => 'nullable|string|max:500',
            'reference' => 'nullable|string|max:120',
        ]);

        $payments->recordManual(
            $request->user(),
            $invoice,
            PaymentMethod::from($data['method']),
            (int) $data['amount_minor'],
            $data['proof_url'] ?? null,
            $data['reference'] ?? null
        );

        return back()->with('status', __('finance.manual_recorded'));
    }

    public function verifyManual(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $payments->verifyManual($request->user(), $payment);

        return back()->with('status', __('finance.manual_verified'));
    }

    public function grantPoints(Request $request, WalletService $wallets): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount_minor' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $wallets->grantPoints(
            $request->user(),
            User::query()->findOrFail($data['student_id']),
            Currency::from($data['currency']),
            (int) $data['amount_minor'],
            $data['note'] ?? null
        );

        return back()->with('status', __('finance.points_granted'));
    }

    public function topUp(Request $request, DonationService $donations): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount_minor' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $donations->topUpWallet(
            $request->user(),
            User::query()->findOrFail($data['student_id']),
            Currency::from($data['currency']),
            (int) $data['amount_minor'],
            $data['note'] ?? null
        );

        return back()->with('status', __('finance.wallet_topped_up'));
    }

    public function approveRefund(Request $request, Refund $refund, PaymentService $payments): RedirectResponse
    {
        $payments->approveRefund($request->user(), $refund);

        return back()->with('status', __('finance.refund_approved'));
    }

    public function reports(Request $request, AuthorizeService $authorize): View
    {
        $this->authorizeFinanceReports($request->user(), $authorize);

        $outstandingByCurrency = [];
        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Partial])
            ->with('payments')
            ->get();

        foreach ($invoices as $invoice) {
            $key = $invoice->currency->value;
            $outstandingByCurrency[$key] = ($outstandingByCurrency[$key] ?? 0) + $invoice->amountDue();
        }

        $paidRows = Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->selectRaw('currency, SUM(amount_minor) as total_minor')
            ->groupBy('currency')
            ->get();

        $paidByCurrency = [];
        foreach ($paidRows as $row) {
            $currencyKey = $row->currency instanceof Currency
                ? $row->currency->value
                : (string) $row->currency;
            $paidByCurrency[$currencyKey] = (int) $row->total_minor;
        }

        $format = static function (array $minorsByCurrency): array {
            $out = [];
            foreach ($minorsByCurrency as $currency => $minor) {
                $enum = Currency::tryFrom((string) $currency);
                if ($enum === null) {
                    continue;
                }
                $out[$currency] = [
                    'minor' => (int) $minor,
                    'formatted' => Money::fromMinor((int) $minor, $enum)->format(),
                ];
            }
            ksort($out);

            return $out;
        };

        return view('admin.finance.reports', [
            'outstanding' => $format($outstandingByCurrency),
            'paidRevenue' => $format($paidByCurrency),
        ]);
    }

    private function authorizeFinanceReports(User $user, AuthorizeService $authorize): void
    {
        try {
            $authorize->authorize($user, 'finance.invoices');
        } catch (AuthorizationException) {
            $authorize->authorize($user, 'finance.wallet');
        }
    }
}
