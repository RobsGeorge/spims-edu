@extends('layouts.app')

@section('title', __('ui.set_password'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 auth-card">
            <div class="card-body p-4">
                <h1 class="h4 spims-title mb-3">{{ __('ui.set_password') }}</h1>
                <form method="POST" action="{{ route('auth.password.create') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('ui.password') }}</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('ui.password_confirm') }}</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.save') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
