@extends('layouts.app')
@section('title', __('learning.settings'))
@section('content')
<div class="row justify-content-center animate-in">
    <div class="col-lg-7">
        <h1 class="spims-title mb-3">{{ __('learning.settings') }}</h1>
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @include('partials.superadmin-entrance-banner', ['caption' => __('superadmin.entrance_from_settings')])
        @include('partials.features-entrance-banner', ['caption' => __('features.entrance_from_settings')])
        @include('partials.config-entrance-banner', ['caption' => __('system_settings.entrance_from_settings')])
        @include('partials.theme-entrance-banner', ['caption' => __('theme_studio.entrance_from_settings')])
        @include('partials.people-entrance-banner')

        <form method="POST" action="{{ route('settings.picture') }}" enctype="multipart/form-data" class="app-card p-4 mb-4">
            @csrf
            <h2 class="h5 mb-3">{{ __('learning.profile_picture') }}</h2>
            @if($avatarUrl)
                <div class="mb-3">
                    <img src="{{ $avatarUrl }}" alt="{{ __('learning.profile_picture_current') }}" width="96" height="96" class="rounded-circle" style="object-fit: cover;">
                </div>
            @else
                <p class="text-muted mb-3">{{ __('learning.profile_picture_empty') }}</p>
            @endif
            <div class="mb-3">
                <label class="form-label" for="set-picture">{{ __('learning.profile_picture_choose') }}</label>
                <input id="set-picture" type="file" name="picture" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="form-control @error('picture') is-invalid @enderror" required>
                <div class="form-text">{{ __('learning.profile_picture_help') }}</div>
                @error('picture')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ __('learning.profile_picture_save') }}</button>
        </form>

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
                    <label class="form-label" for="set-dob">{{ __('attendance.date_of_birth') }}</label>
                    <input id="set-dob" type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}">
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
                    <a href="{{ route('settings.notifications.edit') }}" class="btn btn-outline-secondary">{{ __('communications.preferences_title') }}</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
