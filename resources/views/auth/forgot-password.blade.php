@extends('layouts.app')

@section('title', __('ui.forgot_password'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 auth-card">
            <div class="card-body p-4">
                <h1 class="h4 spims-title mb-3">{{ __('ui.forgot_password') }}</h1>
                <form method="POST" action="{{ url('/forgot-password') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('ui.email') }}</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.send_otp') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
