@extends('layouts.app')

@section('title', __('ui.verify_reset_title'))

@section('content')
        <div class="card border-0 auth-card">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 spims-title mb-2">{{ __('ui.verify_reset_title') }}</h1>
                <p class="spims-text-dim auth-help mb-4">{{ __('ui.auth_help_verify_reset') }}</p>

                @if(!empty($devOtp))
                    <div class="alert alert-warning academic-alert">{{ __('ui.dev_otp', ['code' => $devOtp]) }}</div>
                @endif

                @if(session('status'))
                    <div class="alert alert-success academic-alert" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('auth.password.verify') }}" class="academic-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="reset-code">{{ __('ui.otp_code') }}</label>
                        <input id="reset-code" type="text" name="code" maxlength="6" class="form-control auth-otp-input @error('code') is-invalid @enderror" required inputmode="numeric" autocomplete="one-time-code">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3">{{ __('ui.verify') }}</button>
                </form>
                <div class="auth-footer-links d-flex justify-content-between flex-wrap gap-2">
                    <form method="POST" action="{{ route('auth.password.resend') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 spims-link-btn">{{ __('ui.resend_otp') }}</button>
                    </form>
                    <a href="{{ route('auth.login') }}">{{ __('ui.auth_back_to_login') }}</a>
                </div>
            </div>
        </div>
@endsection
