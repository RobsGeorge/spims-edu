@extends('layouts.app')

@section('title', __('ui.new_password_title'))

@section('content')
        <div class="card border-0 auth-card">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 spims-title mb-2">{{ __('ui.new_password_title') }}</h1>
                <p class="spims-text-dim auth-help mb-4">{{ __('ui.auth_help_new_password') }}</p>
                <form method="POST" action="{{ route('auth.password.reset') }}" class="academic-form">
                    @csrf
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
