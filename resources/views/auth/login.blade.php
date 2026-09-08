@extends('layouts.app')

@section('title', __('ui.login'))

@section('content')
@php
    $emailError = $errors->first('email');
    $isSuspended = $emailError && str_contains($emailError, __('auth.suspended'));
    $isFailed = $emailError && str_contains($emailError, __('auth.failed'));
@endphp
        <div class="card border-0 auth-card">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 spims-title mb-2"><span class="spims-heading-icon-wrap" aria-hidden="true"><x-icon name="login" class="spims-heading-icon" /></span><span>{{ __('ui.login') }}</span></h1>
                <p class="text-muted-theme auth-help mb-4">{{ __('ui.auth_help_login') }}</p>

                @if(session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                @if($isSuspended)
                    <div class="alert alert-danger" role="alert">{{ __('auth.suspended') }}</div>
                @elseif($isFailed)
                    <div class="alert alert-danger" role="alert">{{ __('auth.failed') }}</div>
                @elseif($emailError)
                    <div class="alert alert-danger" role="alert">{{ $emailError }}</div>
                @endif

                <form method="POST" action="{{ route('auth.login') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="login-email">{{ __('ui.email') }}</label>
                        <input id="login-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="login-password">{{ __('ui.password') }}</label>
                        <input id="login-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.login') }}</button>
                </form>
                <div class="auth-footer-links d-flex justify-content-between flex-wrap gap-2">
                    <a href="{{ route('auth.register') }}">{{ __('ui.register') }}</a>
                    <a href="{{ route('auth.password.request') }}">{{ __('ui.forgot_password') }}</a>
                </div>
            </div>
        </div>
@endsection
