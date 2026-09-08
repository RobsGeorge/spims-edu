@extends('layouts.app')
@section('title', __('finance.checkout'))
@section('content')
@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<x-page-header :title="__('finance.checkout')">
    <x-slot:subtitle>
        <x-badge :value="$invoice->status" />
        &mdash;
        <x-money :minor="$invoice->total_minor" :currency="$invoice->currency" />
    </x-slot:subtitle>
</x-page-header>
<p class="spims-text-dim mb-3">
    {{ __('finance.paid') }}: {{ $invoice->amountPaid() }} &middot; {{ __('finance.due') }}: {{ $invoice->amountDue() }}
</p>

@include('finance._payment-plan', ['invoice' => $invoice, 'showPayButtons' => true])

@if($invoice->amountDue() > 0)
<x-card variant="panel" tag="form" method="POST" action="{{ route('finance.checkout', $invoice) }}" class="mb-3">
    @csrf
    <div class="row g-2 p-3">
        <div class="col-md-4">
            <label class="form-label">{{ __('finance.wallet_money') }}</label>
            <input type="number" name="wallet_money" class="form-control" value="0" min="0">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('finance.wallet_points') }}</label>
            <input type="number" name="wallet_points" class="form-control" value="0" min="0">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('finance.gateway') }}</label>
            <select name="gateway" class="form-select">
                <option value="">auto</option>
                <option value="PAYPAL">PayPal</option>
                <option value="PAYMOB">Paymob</option>
                <option value="CASHIER">Cashier</option>
            </select>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">
                {{ $invoice->openPaymentPlan() ? __('finance.pay_remaining') : __('finance.checkout') }}
            </button>
        </div>
    </div>
</x-card>
@endif

@if($invoice->payments->isNotEmpty())
<ul class="mt-3 list-unstyled">
@foreach($invoice->payments as $payment)
    <li class="mb-3 d-flex flex-wrap align-items-center gap-2">
        <x-badge :value="$payment->status" />
        &middot;
        <x-money :minor="$payment->amount_minor" :currency="$payment->currency" />
        @if($payment->status === \App\Enums\PaymentStatus::Completed || $payment->receipt_serial)
            @if($payment->receipt_serial)
                &middot; {{ $payment->receipt_serial }}
            @endif
            <a href="{{ route('finance.receipts.show', $payment) }}" class="ms-1">{{ __('finance.view_receipt') }}</a>
        @endif
        @include('finance._refund-request-form', ['payment' => $payment])
    </li>
@endforeach
</ul>
@endif
@endsection
