@php
    $refundCap = $payment->amount_minor;
    $refundDefault = old('amount_minor', $payment->amount_minor);
@endphp
@if($payment->isRefundable())
<form method="POST" action="{{ route('finance.refund-request', $payment) }}" class="row g-2 align-items-end mt-2">
    @csrf
    <div class="col-12 col-md-3">
        <label class="form-label" for="refund-amount-{{ $payment->id }}">{{ __('finance.refund_amount') }}</label>
        <input id="refund-amount-{{ $payment->id }}" type="number" name="amount_minor" class="form-control form-control-sm" min="1" max="{{ $refundCap }}" value="{{ $refundDefault }}" required>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label" for="refund-reason-{{ $payment->id }}">{{ __('finance.refund_reason') }}</label>
        <input id="refund-reason-{{ $payment->id }}" name="reason" class="form-control form-control-sm" maxlength="500" value="{{ old('reason') }}">
    </div>
    <div class="col-12 col-md-3">
        <div class="form-check mt-4">
            <input id="refund-points-{{ $payment->id }}" type="checkbox" class="form-check-input" name="as_points" value="1" @checked(old('as_points'))>
            <label class="form-check-label" for="refund-points-{{ $payment->id }}">{{ __('finance.refund_as_points') }}</label>
        </div>
    </div>
    <div class="col-12 col-md-2">
        <button class="btn btn-sm btn-outline-primary w-100">{{ __('finance.request_refund') }}</button>
    </div>
</form>
@endif
