@extends('layouts.app')

@section('title', __('ui.verify_email'))

@section('content')
        <div class="card border-0 auth-card">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 spims-title mb-2"><span class="spims-heading-icon-wrap" aria-hidden="true"><x-icon name="lock" class="spims-heading-icon" /></span><span>{{ __('ui.verify_email') }}</span></h1>
                <p class="text-muted-theme auth-help mb-4">{{ __('ui.auth_help_verify') }}</p>
                @if(!empty($devOtp))
                    <div class="alert alert-warning">{{ __('ui.dev_otp', ['code' => $devOtp]) }}</div>
                @endif
                <form method="POST" action="{{ route('auth.verify') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="verify-code">{{ __('ui.otp_code') }}</label>
                        <input id="verify-code" type="text" name="code" maxlength="6" class="form-control auth-otp-input @error('code') is-invalid @enderror" required inputmode="numeric" autocomplete="one-time-code">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.verify') }}</button>
                </form>
            </div>
        </div>
@endsection
