@extends('layouts.app')
@section('title', __('attendance.check_in'))
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <x-page-header :title="__('attendance.check_in')" :subtitle="__('attendance.check_in_help')" />
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('attendance.check-in.store') }}" class="app-card p-4">
            @csrf
            <label class="form-label" for="check-in-code">{{ __('attendance.check_in_code') }}</label>
            <input id="check-in-code" name="code" class="form-control mb-3" required autocomplete="one-time-code">
            <button class="btn btn-primary">{{ __('attendance.check_in_submit') }}</button>
        </form>
    </div>
</div>
@endsection
