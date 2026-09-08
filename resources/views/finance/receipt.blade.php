@extends('layouts.app')
@section('title', __('finance.receipt_title'))
@section('content')
@php
    use App\Support\Money;
@endphp
<x-page-header :title="__('finance.receipt_title')" />

<x-card variant="panel">
    <dl class="row mb-0 p-3">
        <dt class="col-sm-4">{{ __('finance.receipt_serial') }}</dt>
        <dd class="col-sm-8"><code>{{ $payment->receipt_serial }}</code></dd>

        <dt class="col-sm-4">{{ __('finance.amount') }}</dt>
        <dd class="col-sm-8">
            <x-money :minor="$payment->amount_minor" :currency="$payment->currency" />
        </dd>

        <dt class="col-sm-4">{{ __('ui.status') }}</dt>
        <dd class="col-sm-8"><x-badge :value="$payment->status" /></dd>

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
</x-card>

<div class="mt-3 d-flex gap-2">
    <a href="{{ route('finance.index') }}" class="btn btn-outline-secondary">{{ __('finance.back_to_finance') }}</a>
    @if($payment->invoice)
        <a href="{{ route('finance.invoices.show', $payment->invoice) }}" class="btn btn-outline-primary">{{ __('finance.checkout') }}</a>
    @endif
</div>
@endsection
