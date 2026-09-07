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
<h1 class="spims-title mb-3">{{ __('finance.checkout') }}</h1>
<p>{{ $invoice->status->value }} — {{ $invoice->total_minor }} {{ $invoice->currency->value }}</p>
<p>{{ __('finance.paid') }}: {{ $invoice->amountPaid() }} · {{ __('finance.due') }}: {{ $invoice->amountDue() }}</p>

@include('finance._payment-plan', ['invoice' => $invoice, 'showPayButtons' => true])

@if($invoice->amountDue() > 0)
<form method="POST" action="{{ route('finance.checkout', $invoice) }}" class="card border-0 shadow-sm">
    @csrf
    <div class="card-body row g-2">
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
</form>
@endif

@if($invoice->payments->isNotEmpty())
<ul class="mt-3 list-unstyled">
@foreach($invoice->payments as $payment)
    <li class="mb-3">
        {{ $payment->status->value }} · {{ $payment->amount_minor }}
        @if($payment->status === \App\Enums\PaymentStatus::Completed || $payment->receipt_serial)
            @if($payment->receipt_serial)
                · {{ $payment->receipt_serial }}
            @endif
            <a href="{{ route('finance.receipts.show', $payment) }}" class="ms-1">{{ __('finance.view_receipt') }}</a>
        @endif
        @include('finance._refund-request-form', ['payment' => $payment])
    </li>
@endforeach
</ul>
@endif
@endsection
