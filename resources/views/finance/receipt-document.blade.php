<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('finance.receipt_title') }} — {{ $payment->receipt_serial }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #1a1a1a; }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; }
        dl { display: grid; grid-template-columns: 12rem 1fr; gap: 0.5rem 1rem; }
        dt { font-weight: 600; }
        dd { margin: 0; }
        code { font-size: 0.95rem; }
    </style>
</head>
<body>
@php
    use App\Support\Money;
@endphp
    <h1>{{ __('finance.receipt_title') }}</h1>
    <dl>
        <dt>{{ __('finance.receipt_serial') }}</dt>
        <dd><code>{{ $payment->receipt_serial }}</code></dd>

        <dt>{{ __('finance.amount') }}</dt>
        <dd>{{ Money::fromMinor((int) $payment->amount_minor, $payment->currency)->format() }}</dd>

        <dt>{{ __('finance.currency') }}</dt>
        <dd>{{ $payment->currency->value }}</dd>

        <dt>{{ __('ui.status') }}</dt>
        <dd>{{ $payment->status->value }}</dd>

        @if($payment->invoice_id)
            <dt>{{ __('finance.invoices') }}</dt>
            <dd>{{ $payment->invoice_id }}</dd>
        @endif

        <dt>{{ __('finance.paid') }}</dt>
        <dd>{{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
    </dl>
</body>
</html>
