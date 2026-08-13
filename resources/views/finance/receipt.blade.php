@extends('layouts.app')
@section('title', __('finance.receipt_title'))
@section('content')
@php
    use App\Support\Money;
@endphp
<h1 class="spims-title mb-3">{{ __('finance.receipt_title') }}</h1>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">{{ __('finance.receipt_serial') }}</dt>
            <dd class="col-sm-8"><code>{{ $payment->receipt_serial }}</code></dd>

            <dt class="col-sm-4">{{ __('finance.amount') }}</dt>
            <dd class="col-sm-8">{{ Money::fromMinor((int) $payment->amount_minor, $payment->currency)->format() }}</dd>

            <dt class="col-sm-4">{{ __('finance.currency') }}</dt>
            <dd class="col-sm-8">{{ $payment->currency->value }}</dd>

            <dt class="col-sm-4">{{ __('ui.status') }}</dt>
            <dd class="col-sm-8">{{ $payment->status->value }}</dd>

            @if($payment->invoice)
                <dt class="col-sm-4">{{ __('finance.invoices') }}</dt>
                <dd class="col-sm-8">
                    <a href="{{ route('finance.invoices.show', $payment->invoice) }}">
                        {{ \Illuminate\Support\Str::limit($payment->invoice_id, 8, '') }}
                    </a>
                </dd>
            @endif

            <dt class="col-sm-4">{{ __('finance.paid') }}</dt>
            <dd class="col-sm-8">{{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
        </dl>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <a href="{{ route('finance.index') }}" class="btn btn-outline-secondary">{{ __('finance.back_to_finance') }}</a>
    @if($payment->invoice)
        <a href="{{ route('finance.invoices.show', $payment->invoice) }}" class="btn btn-outline-primary">{{ __('finance.checkout') }}</a>
    @endif
</div>
@endsection
