@extends('layouts.app')
@section('title', __('finance.my_finance'))
@section('content')
@php
    use App\Enums\Currency;
    use App\Enums\PaymentStatus;
    use App\Enums\WalletKind;
    use App\Support\Money;
@endphp
@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
<h1 class="spims-title mb-3">{{ __('finance.my_finance') }}</h1>

<div class="row g-3 mb-4 wallet-balance-cards">
    <div class="col-6 col-md-3">
        <div class="wallet-balance-card">
            <span class="wallet-balance-card__label">{{ __('learning.usd_money') }}</span>
            <strong class="wallet-balance-card__value">{{ Money::fromMinor($wallet->balance(Currency::Usd, WalletKind::Money), Currency::Usd)->format() }}</strong>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="wallet-balance-card">
            <span class="wallet-balance-card__label">{{ __('learning.egp_money') }}</span>
            <strong class="wallet-balance-card__value">{{ Money::fromMinor($wallet->balance(Currency::Egp, WalletKind::Money), Currency::Egp)->format() }}</strong>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="wallet-balance-card">
            <span class="wallet-balance-card__label">{{ __('learning.usd_points') }}</span>
            <strong class="wallet-balance-card__value">{{ Money::fromMinor($wallet->balance(Currency::Usd, WalletKind::Points), Currency::Usd)->format() }}</strong>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="wallet-balance-card">
            <span class="wallet-balance-card__label">{{ __('learning.egp_points') }}</span>
            <strong class="wallet-balance-card__value">{{ Money::fromMinor($wallet->balance(Currency::Egp, WalletKind::Points), Currency::Egp)->format() }}</strong>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn btn-sm btn-outline-primary" href="{{ route('donate.create') }}">{{ __('finance.donate') }}</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <x-card variant="panel">
            <h2 class="h6">{{ __('finance.transactions') }}</h2>
            <div class="spims-table-wrap">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('finance.tx_date') }}</th>
                        <th>{{ __('finance.tx_direction') }}</th>
                        <th class="text-end">{{ __('finance.tx_amount') }}</th>
                        <th>{{ __('finance.tx_kind') }}</th>
                        <th>{{ __('finance.tx_reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td class="text-nowrap spims-text-dim small">{{ $tx->created_at->format('Y-m-d') }}</td>
                            <td><x-badge :value="$tx->direction" /></td>
                            <td class="text-end tabular-nums"><x-money :minor="(int) $tx->amount_minor" :currency="$tx->currency" /></td>
                            <td><x-badge :value="$tx->kind" /></td>
                            <td><x-badge :value="$tx->reason" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="spims-text-dim py-3 text-center">{{ __('ui.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if($transactions->hasPages())
                <div class="mt-3">{{ $transactions->links() }}</div>
            @endif
        </x-card>
    </div>
</div>

<h2 class="h5">{{ __('finance.invoices') }}</h2>
<div class="spims-table-wrap">
<table class="table">
    <thead><tr><th>{{ __('finance.invoice_date') }}</th><th>{{ __('finance.total') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('finance.due') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($invoices as $invoice)
        @php $overdue = $invoice->due_date && $invoice->due_date->isPast() && $invoice->amountDue() > 0; @endphp
        <tr>
            <td class="text-nowrap small spims-text-dim">{{ $invoice->created_at->format('Y-m-d') }}</td>
            <td><x-money :minor="(int) $invoice->total_minor" :currency="$invoice->currency" /></td>
            <td><x-badge :value="$invoice->status" /></td>
            <td class="tabular-nums">
                @if($overdue)
                    <span class="text-danger fw-semibold"><x-money :minor="(int) $invoice->amountDue()" :currency="$invoice->currency" /></span>
                @else
                    <x-money :minor="(int) $invoice->amountDue()" :currency="$invoice->currency" />
                @endif
            </td>
            <td>
                <a href="{{ route('finance.invoices.show', $invoice) }}">{{ __('finance.pay') }}</a>
                @if($invoice->openPaymentPlan())
                    <span class="badge spims-badge spims-badge--info ms-1">{{ __('finance.plan_badge') }}</span>
                @elseif($invoice->amountDue() > 0 && $invoice->amountPaid() === 0)
                    <span class="small ms-1">{{ __('finance.pay_in_n', ['n' => 3]) }}</span>
                @endif
                @foreach($invoice->payments as $payment)
                    @if($payment->status === PaymentStatus::Completed || $payment->receipt_serial)
                        <a href="{{ route('finance.receipts.show', $payment) }}" class="ms-2">{{ __('finance.view_receipt') }}</a>
                    @endif
                    @include('finance._refund-request-form', ['payment' => $payment])
                @endforeach
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@if($invoices->hasPages())
    <div class="mt-3">{{ $invoices->links() }}</div>
@endif
@endsection
