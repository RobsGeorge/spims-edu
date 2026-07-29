@extends('layouts.app')

@section('title', __('ui.register'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card border-0 auth-card">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 spims-title mb-2">{{ __('ui.register') }}</h1>
                <p class="text-muted-theme mb-4">{{ __('ui.home_cta_primary') }}</p>
                <form method="POST" action="{{ route('auth.register') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="reg-first">{{ __('ui.first_name') }}</label>
                            <input id="reg-first" type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="reg-last">{{ __('ui.last_name') }}</label>
                            <input id="reg-last" type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required>
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="reg-email">{{ __('ui.email') }}</label>
                            <input id="reg-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="reg-phone">{{ __('ui.phone') }}</label>
                            <input id="reg-phone" type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="reg-locale">{{ __('ui.locale') }}</label>
                            <select id="reg-locale" name="preferred_locale" class="form-select">
                                @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                                    <option value="{{ $code }}" @selected(old('preferred_locale', app()->getLocale()) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4">{{ __('ui.continue') }}</button>
                </form>
                <p class="mt-3 mb-0"><a href="{{ route('auth.login') }}">{{ __('ui.login') }}</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
