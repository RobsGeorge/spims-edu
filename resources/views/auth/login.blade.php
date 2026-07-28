@extends('layouts.app')

@section('title', __('ui.login'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 spims-title mb-3">{{ __('ui.login') }}</h1>
                @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                <form method="POST" action="{{ route('auth.login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('ui.email') }}</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('ui.password') }}</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.login') }}</button>
                </form>
                <div class="mt-3 d-flex justify-content-between">
                    <a href="{{ route('auth.register') }}">{{ __('ui.register') }}</a>
                    <a href="{{ route('auth.password.request') }}">{{ __('ui.forgot_password') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
