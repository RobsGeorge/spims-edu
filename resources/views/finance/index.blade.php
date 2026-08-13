@extends('layouts.app')
@section('title', __('finance.my_finance'))
@section('content')
@php
    use App\Enums\Currency;
    use App\Enums\WalletKind;
    use App\Support\Money;
@endphp
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
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6">{{ __('finance.transactions') }}</h2>
                <ul class="small mb-0">
                    @forelse($transactions as $tx)
                        <li>{{ $tx->direction->value }} {{ $tx->amount_minor }} {{ $tx->currency->value }} ({{ $tx->kind->value }} / {{ $tx->reason->value }})</li>
                    @empty
                        <li>{{ __('ui.empty') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
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
            <td>{{ $invoice->total_minor }} {{ $invoice->currency->value }}</td>
            <td>{{ $invoice->status->value }}</td>
            <td>{{ $invoice->amountDue() }}</td>
            <td><a href="{{ route('finance.invoices.show', $invoice) }}">{{ __('finance.pay') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
