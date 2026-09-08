@extends('layouts.app')
@section('title', __('finance.donate'))
@section('content')
<x-page-header :title="__('finance.donate')" />
<x-card variant="panel" tag="form" method="POST" action="{{ route('donate.store') }}">
    @csrf
    <div class="row g-2 p-3">
        <div class="col-md-4">
            <label class="form-label">{{ __('finance.currency') }}</label>
            <select name="currency" class="form-select" required>
                <option value="USD">USD</option>
                <option value="EGP">EGP</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('finance.amount_minor') }}</label>
            <input type="number" name="amount_minor" class="form-control" min="1" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('finance.designation') }}</label>
            <input type="text" name="designation" class="form-control">
        </div>
        <div class="col-12"><button class="btn btn-primary">{{ __('finance.donate') }}</button></div>
    </div>
</x-card>
@endsection
