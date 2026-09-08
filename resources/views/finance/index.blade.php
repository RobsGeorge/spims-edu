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
            <ul class="small mb-0">
                @forelse($transactions as $tx)
                    <li class="d-flex flex-wrap align-items-center gap-1 py-1">
                        <x-badge :value="$tx->direction" />
                        <x-money :minor="(int) $tx->amount_minor" :currency="$tx->currency" />
                        <span class="spims-text-dim">(</span><x-badge :value="$tx->kind" /><span class="spims-text-dim"> / </span><x-badge :value="$tx->reason" /><span class="spims-text-dim">)</span>
                    </li>
                @empty
                    <li>{{ __('ui.empty') }}</li>
                @endforelse
            </ul>
            @if($transactions->hasPages())
                <div class="mt-3">{{ $transactions->links() }}</div>
            @endif
        </x-card>
    </div>
</div>

<h2 class="h5">{{ __('finance.invoices') }}</h2>
<div class="spims-table-wrap">
<table class="table">
    <thead><tr><th>ID</th><th>{{ __('finance.total') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('finance.due') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($invoices as $invoice)
        <tr>
            <td>{{ \Illuminate\Support\Str::limit($invoice->id, 8, '') }}</td>
            <td><x-money :minor="(int) $invoice->total_minor" :currency="$invoice->currency" /></td>
            <td><x-badge :value="$invoice->status" /></td>
            <td>{{ $invoice->amountDue() }}</td>
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
