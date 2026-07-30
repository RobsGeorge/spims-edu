@extends('layouts.app')

@section('title', __('ui.nav_theme'))

@section('content')
<x-page-header :title="__('ui.nav_theme')" :subtitle="__('hubs.theme_desc')" />

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@php
    $tokens = \App\Support\ThemeTokens::resolve($theme->tokens);
    $lightPrimary = old('tokens.light.primary', $tokens['light']['primary'] ?? '#5d0326');
    $lightBg = old('tokens.light.bg1', $tokens['light']['bg1'] ?? '#f8f9ff');
    $lightAccent = old('tokens.light.accent', $tokens['light']['accent'] ?? '#eac167');
    $darkPrimary = old('tokens.dark.primary', $tokens['dark']['primary'] ?? '#ffb1c0');
    $darkBg = old('tokens.dark.bg1', $tokens['dark']['bg1'] ?? '#0d1322');
    $darkAccent = old('tokens.dark.accent', $tokens['dark']['accent'] ?? '#e9c16d');
@endphp

<div class="spims-theme-preview card border-0 shadow-sm mb-4">
    <div class="card-body">
        <p class="small text-muted-theme mb-2">{{ __('ui.theme_preview') }}</p>
        <div class="d-flex flex-wrap gap-3 align-items-stretch">
            <div class="flex-grow-1 rounded-3 p-3" style="background: {{ $lightBg }}; min-width: 12rem;">
                <div class="small mb-2" style="color: {{ $lightPrimary }};">{{ __('ui.theme_light') }}</div>
                <div class="d-flex gap-2">
                    <span class="rounded-2 flex-grow-1" style="height: 2rem; background: {{ $lightPrimary }};" title="primary"></span>
                    <span class="rounded-2 flex-grow-1" style="height: 2rem; background: {{ $lightBg }}; border: 1px solid rgba(0,0,0,.12);" title="bg1"></span>
                    <span class="rounded-2 flex-grow-1" style="height: 2rem; background: {{ $lightAccent }};" title="accent"></span>
                </div>
            </div>
            <div class="flex-grow-1 rounded-3 p-3" style="background: {{ $darkBg }}; min-width: 12rem;">
                <div class="small mb-2" style="color: {{ $darkPrimary }};">{{ __('ui.theme_dark') }}</div>
                <div class="d-flex gap-2">
                    <span class="rounded-2 flex-grow-1" style="height: 2rem; background: {{ $darkPrimary }};" title="primary"></span>
                    <span class="rounded-2 flex-grow-1" style="height: 2rem; background: {{ $darkBg }}; border: 1px solid rgba(255,255,255,.2);" title="bg1"></span>
                    <span class="rounded-2 flex-grow-1" style="height: 2rem; background: {{ $darkAccent }};" title="accent"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.theme.update', $theme) }}" id="theme-editor-form">
    @csrf
    @method('PUT')
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ __('ui.theme_name') }}</label>
                <input name="name" class="form-control" value="{{ old('name', $theme->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('ui.site_name') }}</label>
                <input name="site_name" class="form-control" value="{{ old('site_name', $theme->site_name) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('ui.logo_light_url') }}</label>
                <input type="url" name="logo_light_url" class="form-control" value="{{ old('logo_light_url', $theme->logo_light_url) }}" placeholder="https://">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('ui.logo_dark_url') }}</label>
                <input type="url" name="logo_dark_url" class="form-control" value="{{ old('logo_dark_url', $theme->logo_dark_url) }}" placeholder="https://">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('ui.favicon_url') }}</label>
                <input type="url" name="favicon_url" class="form-control" value="{{ old('favicon_url', $theme->favicon_url) }}" placeholder="https://">
            </div>
            <div class="col-12">
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $theme->is_active))>
                    {{ __('ui.theme_active') }}
                </label>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">{{ __('ui.theme_tokens') }}</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <h3 class="h6 text-muted-theme">{{ __('ui.theme_light') }}</h3>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label">{{ __('ui.token_primary') }}</label>
                            <input type="color" name="tokens[light][primary]" class="form-control form-control-color w-100" value="{{ $lightPrimary }}" data-preview="light-primary">
                        </div>
                        <div class="col-4">
                            <label class="form-label">{{ __('ui.token_bg') }}</label>
                            <input type="color" name="tokens[light][bg1]" class="form-control form-control-color w-100" value="{{ $lightBg }}" data-preview="light-bg">
                        </div>
                        <div class="col-4">
                            <label class="form-label">{{ __('ui.token_accent') }}</label>
                            <input type="color" name="tokens[light][accent]" class="form-control form-control-color w-100" value="{{ $lightAccent }}" data-preview="light-accent">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-muted-theme">{{ __('ui.theme_dark') }}</h3>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label">{{ __('ui.token_primary') }}</label>
                            <input type="color" name="tokens[dark][primary]" class="form-control form-control-color w-100" value="{{ $darkPrimary }}" data-preview="dark-primary">
                        </div>
                        <div class="col-4">
                            <label class="form-label">{{ __('ui.token_bg') }}</label>
                            <input type="color" name="tokens[dark][bg1]" class="form-control form-control-color w-100" value="{{ $darkBg }}" data-preview="dark-bg">
                        </div>
                        <div class="col-4">
                            <label class="form-label">{{ __('ui.token_accent') }}</label>
                            <input type="color" name="tokens[dark][accent]" class="form-control form-control-color w-100" value="{{ $darkAccent }}" data-preview="dark-accent">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button class="btn btn-primary">{{ __('ui.save') }}</button>
</form>
@endsection
