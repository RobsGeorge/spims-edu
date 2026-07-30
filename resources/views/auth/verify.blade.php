@extends('layouts.app')

@section('title', __('ui.verify_email'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 auth-card">
            <div class="card-body p-4">
                <h1 class="h4 spims-title mb-3">{{ __('ui.verify_email') }}</h1>
                <p class="text-muted-theme">{{ __('auth.otp_sent_log') }}</p>
                @if(!empty($devOtp))
                    <div class="alert alert-warning">{{ __('ui.dev_otp', ['code' => $devOtp]) }}</div>
                @endif
                <form method="POST" action="{{ route('auth.verify') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('ui.otp_code') }}</label>
                        <input type="text" name="code" maxlength="6" class="form-control @error('code') is-invalid @enderror" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.verify') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
