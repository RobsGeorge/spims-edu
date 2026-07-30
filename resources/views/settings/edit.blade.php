@extends('layouts.app')
@section('title', __('learning.settings'))
@section('content')
<div class="row justify-content-center animate-in">
    <div class="col-lg-7">
        <h1 class="spims-title mb-3">{{ __('learning.settings') }}</h1>
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <form method="POST" action="{{ route('settings.update') }}" class="app-card p-4">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="set-first">{{ __('learning.first_name') }}</label>
                    <input id="set-first" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $user->first_name) }}" required>
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="set-last">{{ __('learning.last_name') }}</label>
                    <input id="set-last" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $user->last_name) }}" required>
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="set-phone">{{ __('learning.phone') }}</label>
                    <input id="set-phone" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="set-locale">{{ __('learning.preferred_locale') }}</label>
                    <select id="set-locale" name="preferred_locale" class="form-select">
                        @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('preferred_locale', $user->preferred_locale) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="set-theme">{{ __('learning.theme_preference') }}</label>
                    <select id="set-theme" name="theme_preference" class="form-select">
                        @foreach(['LIGHT' => __('ui.theme_light'), 'DARK' => __('ui.theme_dark'), 'SYSTEM' => __('ui.theme_system')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('theme_preference', $user->theme_preference?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-check">
                        <input type="checkbox" name="notify_email" value="1" class="form-check-input" @checked(old('notify_email', $user->notify_email))>
                        <span class="form-check-label">{{ __('learning.notify_email') }}</span>
                    </label>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">{{ __('ui.save') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
