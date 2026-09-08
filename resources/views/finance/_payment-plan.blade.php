@php
    use App\Enums\InvoiceStatus;
    $openPlan = $invoice->openPaymentPlan();
    $canCreatePlan = $invoice->amountDue() > 0
        && $invoice->amountPaid() === 0
        && $invoice->status === InvoiceStatus::Open
        && $openPlan === null;
    $action = $action ?? route('finance.payment-plan.store', $invoice);
    $showPayButtons = $showPayButtons ?? false;
@endphp

@if($canCreatePlan)
<x-card variant="panel" tag="form" method="POST" action="{{ $action }}" class="mb-3">
    @csrf
    <div class="row g-2 align-items-end">
        <div class="col-12">
            <h2 class="h6 mb-0">{{ __('finance.pay_in_n', ['n' => 3]) }}</h2>
            <p class="small spims-text-dim mb-0">{{ __('finance.plan_help') }}</p>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="installment_count_{{ $invoice->id }}">{{ __('finance.installment_count') }}</label>
            <input id="installment_count_{{ $invoice->id }}" type="number" name="installment_count" class="form-control" value="3" min="2" max="12" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="start_on_{{ $invoice->id }}">{{ __('finance.plan_start_on') }}</label>
            <input id="start_on_{{ $invoice->id }}" type="date" name="start_on" class="form-control" value="{{ now()->toDateString() }}">
        </div>
        <div class="col-12 col-md-4">
            <button class="btn btn-outline-primary">{{ __('finance.create_plan') }}</button>
        </div>
    </div>
</x-card>
@endif

@if($openPlan)
<x-card variant="panel" class="mb-3">
    <h2 class="h6">{{ __('finance.installment_schedule') }}</h2>
    <p class="small mb-2">
        {{ __('finance.plan_status') }}:
        <x-badge :value="$openPlan->status" />
        · {{ $openPlan->installment_count }} ×
        <x-badge :value="$openPlan->interval" />
    </p>
    <div class="spims-table-wrap">
    <table class="table table-sm mb-0">
        <thead>
            <tr>
                <th>{{ __('finance.due') }}</th>
                <th>{{ __('finance.amount') }}</th>
                <th>{{ __('ui.status') }}</th>
                @if($showPayButtons)<th></th>@endif
            </tr>
        </thead>
        <tbody>
        @foreach($openPlan->installments as $installment)
            <tr>
                <td>{{ $installment->due_on?->toDateString() }}</td>
                <td><x-money :minor="(int) $installment->amount_minor" :currency="$installment->currency" /></td>
                <td><x-badge :value="$installment->status" /></td>
                @if($showPayButtons)
                <td>
                    @if($installment->isOpen())
                    <form method="POST" action="{{ route('finance.installments.pay', [$invoice, $installment]) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-primary">{{ __('finance.pay_installment') }}</button>
                    </form>
                    @endif
                </td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @if($showPayButtons && $invoice->amountDue() > 0)
    <p class="small mt-3 mb-0">{{ __('finance.early_payoff_help') }}</p>
    @endif
</x-card>
@endif
