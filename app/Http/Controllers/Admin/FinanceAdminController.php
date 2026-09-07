<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\DonationService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentPlanService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\WalletService;
use App\Services\Reports\ReportService;
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
            'invoices' => Invoice::query()->with(['student', 'paymentPlans.installments'])->latest()->limit(50)->get(),
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

    public function showInvoice(Invoice $invoice): View
    {
        $invoice->load(['student', 'lines', 'payments', 'paymentPlans.installments']);

        return view('admin.finance.invoice', [
            'invoice' => $invoice,
        ]);
    }

    public function attachPaymentPlan(Request $request, Invoice $invoice, PaymentPlanService $plans): RedirectResponse
    {
        $data = $request->validate([
            'installment_count' => 'required|integer|min:2|max:12',
            'start_on' => 'nullable|date',
        ]);

        $plans->create(
            $request->user(),
            $invoice,
            (int) $data['installment_count'],
            isset($data['start_on']) ? \Illuminate\Support\Carbon::parse($data['start_on']) : null
        );

        return back()->with('status', __('finance.plan_created'));
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

    public function reports(Request $request, ReportService $reports): View
    {
        $reports->authorizeFinance($request->user());
        $summary = $reports->financeSummary();

        return view('admin.finance.reports', [
            'outstanding' => $summary['outstanding'],
            'paidRevenue' => $summary['paidRevenue'],
            'aging' => $summary['aging'],
        ]);
    }
}
