@extends('layouts.app')

@section('title', __('ui.reset_password'))

@section('content')
        <div class="card border-0 auth-card">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 spims-title mb-2">{{ __('ui.reset_password') }}</h1>
                <p class="text-muted-theme auth-help mb-4">{{ __('ui.auth_help_reset') }}</p>
                @if(!empty($devOtp))
                    <div class="alert alert-warning">{{ __('ui.dev_otp', ['code' => $devOtp]) }}</div>
                @endif
                <form method="POST" action="{{ route('auth.password.reset') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="reset-code">{{ __('ui.otp_code') }}</label>
                        <input id="reset-code" type="text" name="code" maxlength="6" class="form-control auth-otp-input @error('code') is-invalid @enderror" required inputmode="numeric" autocomplete="one-time-code">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reset-password">{{ __('ui.password') }}</label>
                        <input id="reset-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reset-password-confirm">{{ __('ui.password_confirm') }}</label>
                        <input id="reset-password-confirm" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.reset_password') }}</button>
                </form>
                <p class="auth-footer-links mb-0"><a href="{{ route('auth.login') }}">{{ __('ui.auth_back_to_login') }}</a></p>
            </div>
        </div>
@endsection
