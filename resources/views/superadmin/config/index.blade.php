@extends('layouts.app')

@section('title', __('system_settings.page_title'))

@section('content')
<div class="sa-config animate-in">
    <nav class="mb-3 small" aria-label="{{ __('system_settings.page_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('system_settings.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('system_settings.page_title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('system_settings.page_title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('system_settings.page_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('system_settings.page_help') }}</p>
    </header>

    <aside class="sa-callout sa-callout-danger mb-4" role="note">
        <i class="bi bi-eye-slash-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('system_settings.never_title') }}</strong>
            <p class="mb-0">{{ __('system_settings.never_body') }}</p>
        </div>
    </aside>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.features') }}">{{ __('system_settings.open_features') }}</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.reports') }}">{{ __('school_reports.nav_reports') }}</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.ops') }}">{{ __('ops.nav_ops') }}</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('superadmin.status') }}">{{ __('status.nav_status') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.audit.index', ['action' => 'system_settings.']) }}">{{ __('system_settings.open_audit') }}</a>
    </div>
    <p class="small text-muted-theme mb-4">{{ __('system_settings.open_features_help') }}</p>

    <section class="app-card p-3 mb-4">
        <h2 class="h6 page-title">{{ __('system_settings.allowlist_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('system_settings.allowlist_help') }}</p>

        <form method="POST" action="{{ route('superadmin.config.update') }}" class="row g-3">
            @csrf
            @method('PUT')
            @foreach($settings as $row)
                @php
                    $fieldId = 'setting-'.str_replace('.', '-', $row['key']);
                    $oldSettings = old('settings');
                    $current = is_array($oldSettings) && array_key_exists($row['key'], $oldSettings)
                        ? $oldSettings[$row['key']]
                        : $row['value'];
                    $type = $row['type'];
                    $fieldName = 'settings['.$row['key'].']';
                @endphp
                <div class="col-12">
                    <label class="form-label" for="{{ $fieldId }}">{{ __('system_settings.'.$row['key']) }}</label>
                    @if($type === 'locale')
                        <select id="{{ $fieldId }}" name="{{ $fieldName }}" class="form-select">
                            @foreach($row['definition']['options'] ?? ['ar', 'en', 'fr'] as $code)
                                <option value="{{ $code }}" @selected((string) $current === $code)>{{ __('system_settings.locale_'.$code) }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'timezone')
                        <select id="{{ $fieldId }}" name="{{ $fieldName }}" class="form-select">
                            @foreach($timezones as $zone)
                                <option value="{{ $zone }}" @selected((string) $current === $zone)>{{ $zone }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'int_list')
                        <input id="{{ $fieldId }}" name="{{ $fieldName }}" class="form-control"
                               value="{{ is_array($current) ? implode(', ', $current) : $current }}">
                    @elseif($type === 'int')
                        <input id="{{ $fieldId }}" type="number" name="{{ $fieldName }}" class="form-control"
                               min="{{ $row['definition']['min'] ?? 0 }}"
                               max="{{ $row['definition']['max'] ?? 999999 }}"
                               value="{{ $current }}">
                    @else
                        <input id="{{ $fieldId }}" name="{{ $fieldName }}" class="form-control"
                               maxlength="{{ $row['definition']['max'] ?? 255 }}"
                               value="{{ $current }}">
                    @endif
                    <p class="form-text mb-0">{{ __('system_settings.'.$row['key'].'_help') }} <code>{{ $row['key'] }}</code></p>
                </div>
            @endforeach
            <div class="col-12">
                <button class="btn btn-primary">{{ __('system_settings.save') }}</button>
                <p class="form-text mb-0">{{ __('system_settings.save_help') }}</p>
            </div>
        </form>
    </section>

    <section class="app-card p-3 mb-4">
        <h2 class="h6 page-title">{{ __('system_settings.integrations_title') }}</h2>
        <p class="small text-muted-theme mb-2">{{ __('system_settings.integrations_help') }}</p>
        <p class="small text-muted-theme mb-3">{{ __('system_settings.integrations_never') }}</p>
        <ul class="list-unstyled mb-0">
            @foreach($integrations as $integration)
                <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom border-opacity-25">
                    <span>{{ __('system_settings.integration_'.$integration['id']) }}</span>
                    <span class="sa-integration-pill {{ $integration['configured'] ? 'is-on' : 'is-off' }}">
                        {{ $integration['configured'] ? __('system_settings.configured') : __('system_settings.missing') }}
                    </span>
                </li>
            @endforeach
        </ul>
        <p class="form-text mb-0 mt-3">{{ __('status.entrance_from_config') }}
            <a href="{{ route('superadmin.status') }}">{{ __('status.entrance_cta') }}</a>
        </p>
    </section>
</div>
@endsection
