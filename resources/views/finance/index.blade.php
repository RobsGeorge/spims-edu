@extends('layouts.app')
@section('title', __('finance.my_finance'))
@section('content')
<h1 class="spims-title mb-3">{{ __('finance.my_finance') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6">{{ __('finance.wallet') }}</h2>
                <ul class="mb-0">
                    <li>EGP money: {{ $wallet->egp_money_minor }}</li>
                    <li>USD money: {{ $wallet->usd_money_minor }}</li>
                    <li>EGP points: {{ $wallet->egp_points_minor }}</li>
                    <li>USD points: {{ $wallet->usd_points_minor }}</li>
                </ul>
                <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('donate.create') }}">{{ __('finance.donate') }}</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6">{{ __('finance.transactions') }}</h2>
                <ul class="small mb-0">
                    @forelse($transactions as $tx)
                        <li>{{ $tx->direction->value }} {{ $tx->amount_minor }} {{ $tx->currency->value }} ({{ $tx->kind->value }} / {{ $tx->reason->value }})</li>
                    @empty
                        <li>—</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<h2 class="h5">{{ __('finance.invoices') }}</h2>
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
@endsection
