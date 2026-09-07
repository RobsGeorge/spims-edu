@extends('layouts.app')
@section('title', __('finance.admin_title'))
@section('content')
<h1 class="spims-title mb-3">{{ __('finance.admin_title') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p><a href="{{ route('admin.finance.index') }}">{{ __('finance.admin_title') }}</a></p>
<p>{{ $invoice->student->email }} — {{ $invoice->status->value }} — {{ $invoice->total_minor }} {{ $invoice->currency->value }}</p>
<p>{{ __('finance.paid') }}: {{ $invoice->amountPaid() }} · {{ __('finance.due') }}: {{ $invoice->amountDue() }}</p>

@include('finance._payment-plan', [
    'invoice' => $invoice,
    'showPayButtons' => false,
    'action' => route('admin.finance.payment-plan.store', $invoice),
])

@if($invoice->amountDue() > 0)
<form method="POST" action="{{ route('admin.finance.manual', $invoice) }}" class="card border-0 shadow-sm">
    @csrf
    <div class="card-body row g-2">
        <div class="col-md-4">
            <select name="method" class="form-select">
                <option value="MANUAL_CASH">CASH</option>
                <option value="MANUAL_TRANSFER">TRANSFER</option>
                <option value="MANUAL_CHEQUE">CHEQUE</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="number" name="amount_minor" class="form-control" value="{{ $invoice->amountDue() }}" min="1">
        </div>
        <div class="col-md-4">
            <button class="btn btn-outline-primary">{{ __('finance.pay') }}</button>
        </div>
    </div>
</form>
@endif
@endsection
