@extends('layouts.app')

@section('title', __('ui.forgot_password'))

@section('content')
        <div class="card border-0 auth-card">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 spims-title mb-2"><span class="spims-heading-icon-wrap" aria-hidden="true"><x-icon name="lock" class="spims-heading-icon" /></span><span>{{ __('ui.forgot_password') }}</span></h1>
                <p class="text-muted-theme auth-help mb-4">{{ __('ui.auth_help_forgot') }}</p>
                <form method="POST" action="{{ url('/forgot-password') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="forgot-email">{{ __('ui.email') }}</label>
                        <input id="forgot-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="username">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.send_otp') }}</button>
                </form>
                <p class="auth-footer-links mb-0"><a href="{{ route('auth.login') }}">{{ __('ui.auth_back_to_login') }}</a></p>
            </div>
        </div>
@endsection
