@extends('layouts.app')

@section('title', __('theme_studio.page_title'))

@section('content')
<div class="sa-theme-studio animate-in">
    <nav class="mb-3 small" aria-label="{{ __('theme_studio.page_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('theme_studio.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('theme_studio.page_title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('theme_studio.page_title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('theme_studio.page_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('theme_studio.page_help') }}</p>
    </header>

    <aside class="sa-callout sa-callout-danger mb-3" role="note">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('theme_studio.danger_title') }}</strong>
            <p class="mb-0">{{ __('theme_studio.danger_body') }}</p>
        </div>
    </aside>

    <aside class="sa-callout sa-callout-info mb-4" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('theme_studio.gold_title') }}</strong>
            <p class="mb-0">{{ __('theme_studio.gold_body') }}</p>
        </div>
    </aside>

    <div class="d-flex flex-wrap gap-2 mb-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.theme.edit') }}">{{ __('theme_studio.open_staff') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.audit.index', ['action' => 'theme.']) }}">{{ __('theme_studio.open_audit') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.features') }}">{{ __('theme_studio.open_features') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.reports') }}">{{ __('school_reports.nav_reports') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.ops') }}">{{ __('ops.nav_ops') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.status') }}">{{ __('status.nav_status') }}</a>
    </div>
    <p class="small text-muted-theme mb-4">{{ __('theme_studio.open_staff_help') }}</p>

    <section class="mb-4" id="sa-theme-presets">
        <h2 class="h5 page-title mb-1">{{ __('theme_studio.index_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('theme_studio.index_help') }}</p>
        <div class="row g-3">
            @foreach($themes as $theme)
                @php
                    $resolved = \App\Support\ThemeTokens::resolve($theme->tokens);
                    $swatchPrimary = $resolved['light']['primary'] ?? '#5d0326';
                    $swatchBg = $resolved['light']['bg1'] ?? '#f8f9ff';
                    $swatchAccent = $resolved['light']['accent'] ?? '#eac167';
                @endphp
                <div class="col-12">
                    <article class="app-card p-3 sa-theme-card {{ $theme->is_active ? 'is-active' : 'is-inactive' }}">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                            <div class="min-w-0">
                                <h3 class="h6 page-title mb-1">{{ $theme->name }}</h3>
                                <p class="small text-muted-theme mb-2">{{ $theme->site_name }}</p>
                                <p class="mb-2">
                                    <span class="sa-flag-state {{ $theme->is_active ? 'is-on' : 'is-off' }}">
                                        {{ $theme->is_active ? __('theme_studio.active_badge') : __('theme_studio.inactive_badge') }}
                                    </span>
                                </p>
                                <div class="sa-theme-swatches" aria-hidden="true">
                                    <span style="background: {{ $swatchPrimary }}"></span>
                                    <span style="background: {{ $swatchBg }}"></span>
                                    <span style="background: {{ $swatchAccent }}"></span>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-primary btn-sm" href="{{ route('superadmin.theme.edit', $theme) }}">{{ __('theme_studio.open_editor') }}</a>
                                @if(! $theme->is_active)
                                    <form method="POST" action="{{ route('superadmin.theme.activate', $theme) }}">
                                        @csrf
                                        <button class="btn btn-outline-success btn-sm" onclick="return confirm(@json(__('theme_studio.confirm_activate')))">
                                            {{ __('theme_studio.activate') }}
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('superadmin.theme.duplicate', $theme) }}">
                                    @csrf
                                    <button class="btn btn-outline-secondary btn-sm">{{ __('theme_studio.duplicate') }}</button>
                                </form>
                            </div>
                        </div>
                        <p class="form-text mb-0 mt-3">{{ __('theme_studio.activate_help') }} · {{ __('theme_studio.duplicate_help') }}</p>
                    </article>
                </div>
            @endforeach
        </div>
    </section>

    <section class="app-card p-3" id="sa-theme-create">
        <h2 class="h6 page-title mb-1">{{ __('theme_studio.create_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('theme_studio.create_help') }}</p>
        <form method="POST" action="{{ route('superadmin.theme.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-6">
                <label class="form-label" for="theme-create-name">{{ __('theme_studio.create_name') }}</label>
                <input id="theme-create-name" name="name" class="form-control" value="{{ old('name') }}" required maxlength="100">
                <p class="form-text mb-0">{{ __('theme_studio.create_name_help') }}</p>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-primary">{{ __('theme_studio.create_cta') }}</button>
            </div>
        </form>
    </section>
</div>
@endsection
