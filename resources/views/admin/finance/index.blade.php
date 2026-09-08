@extends('layouts.app')
@section('title', __('finance.admin_title'))
@section('content')
<x-page-header :title="__('finance.admin_title')" />
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

@php
    $studentOptions = $students ?? collect();
@endphp

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-4">
        <form method="POST" action="{{ route('admin.finance.invoices.store') }}" class="card border-0 shadow-sm">
            @csrf
            <div class="card-body">
                <h2 class="h6">{{ __('finance.create_invoice') }}</h2>
                <select name="student_id" class="form-select mb-2" required>
                    <option value="">{{ __('finance.select_student') }}</option>
                    @foreach($studentOptions as $student)
                        <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }} — {{ $student->email }}</option>
                    @endforeach
                </select>
                <select name="currency" class="form-select mb-2"><option>USD</option><option>EGP</option></select>
                <input type="number" name="total_minor" class="form-control mb-2" min="1" required>
                <input name="description" class="form-control mb-2" required>
                <button class="btn btn-primary btn-sm">{{ __('ui.save') }}</button>
            </div>
        </form>
    </div>
    <div class="col-12 col-lg-4">
        <form method="POST" action="{{ route('admin.finance.points') }}" class="card border-0 shadow-sm">
            @csrf
            <div class="card-body">
                <h2 class="h6">{{ __('finance.grant_points') }}</h2>
                <select name="student_id" class="form-select mb-2" required>
                    <option value="">{{ __('finance.select_student') }}</option>
                    @foreach($studentOptions as $student)
                        <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }} — {{ $student->email }}</option>
                    @endforeach
                </select>
                <select name="currency" class="form-select mb-2"><option>USD</option><option>EGP</option></select>
                <input type="number" name="amount_minor" class="form-control mb-2" min="1" required>
                <button class="btn btn-primary btn-sm">{{ __('ui.save') }}</button>
            </div>
        </form>
    </div>
    <div class="col-12 col-lg-4">
        <form method="POST" action="{{ route('admin.finance.top-up') }}" class="card border-0 shadow-sm">
            @csrf
            <div class="card-body">
                <h2 class="h6">{{ __('finance.top_up') }}</h2>
                <select name="student_id" class="form-select mb-2" required>
                    <option value="">{{ __('finance.select_student') }}</option>
                    @foreach($studentOptions as $student)
                        <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }} — {{ $student->email }}</option>
                    @endforeach
                </select>
                <select name="currency" class="form-select mb-2"><option>USD</option><option>EGP</option></select>
                <input type="number" name="amount_minor" class="form-control mb-2" min="1" required>
                <button class="btn btn-primary btn-sm">{{ __('ui.save') }}</button>
            </div>
        </form>
    </div>
</div>

<h2 class="h5">{{ __('finance.pending_manual') }}</h2>
<div class="spims-table-wrap">
<table class="table table-sm">
    <thead><tr><th>Payment</th><th>{{ __('finance.student') }}</th><th>{{ __('finance.total') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($pendingManual as $payment)
        <tr>
            <td>{{ $payment->id }}</td>
            <td>{{ $payment->student->email }}</td>
            <td>{{ $payment->amount_minor }} {{ $payment->currency->value }}</td>
            <td>
                <form method="POST" action="{{ route('admin.finance.verify', $payment) }}">@csrf<button class="btn btn-sm btn-success">{{ __('finance.verify') }}</button></form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

<h2 class="h5">{{ __('finance.invoices') }}</h2>
<div class="spims-table-wrap">
<table class="table table-sm">
    <thead><tr><th>ID</th><th>{{ __('finance.student') }}</th><th>{{ __('finance.total') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($invoices as $invoice)
        <tr>
            <td><a href="{{ route('admin.finance.invoices.show', $invoice) }}">{{ $invoice->id }}</a></td>
            <td>{{ $invoice->student->email }}</td>
            <td>{{ $invoice->total_minor }} {{ $invoice->currency->value }}</td>
            <td>{{ $invoice->status->value }}</td>
            <td>
                <form method="POST" action="{{ route('admin.finance.manual', $invoice) }}" class="d-flex gap-1">
                    @csrf
                    <select name="method" class="form-select form-select-sm">
                        <option value="MANUAL_CASH">CASH</option>
                        <option value="MANUAL_TRANSFER">TRANSFER</option>
                        <option value="MANUAL_CHEQUE">CHEQUE</option>
                    </select>
                    <input type="number" name="amount_minor" class="form-control form-control-sm" value="{{ $invoice->amountDue() }}" min="1">
                    <button class="btn btn-sm btn-outline-primary">{{ __('finance.pay') }}</button>
                </form>
                @if($invoice->openPaymentPlan() === null && $invoice->amountDue() > 0 && $invoice->amountPaid() === 0)
                <form method="POST" action="{{ route('admin.finance.payment-plan.store', $invoice) }}" class="d-flex gap-1 mt-1">
                    @csrf
                    <input type="number" name="installment_count" class="form-control form-control-sm" value="3" min="2" max="12" aria-label="{{ __('finance.installment_count') }}">
                    <button class="btn btn-sm btn-outline-secondary">{{ __('finance.pay_in_n', ['n' => 3]) }}</button>
                </form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

<h2 class="h5">{{ __('finance.refunds') }}</h2>
<div class="spims-table-wrap">
<table class="table table-sm">
    <thead><tr><th>ID</th><th>{{ __('finance.student') }}</th><th>{{ __('finance.total') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($refunds as $refund)
        <tr>
            <td>{{ $refund->id }}</td>
            <td>{{ $refund->student->email }}</td>
            <td>{{ $refund->amount_minor }} {{ $refund->currency->value }}</td>
            <td>{{ $refund->status->value }}</td>
            <td>
                @if($refund->status->value === 'REQUESTED')
                <form method="POST" action="{{ route('admin.finance.refunds.approve', $refund) }}">@csrf<button class="btn btn-sm btn-primary">{{ __('finance.approve') }}</button></form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
