@extends('layouts.app')

@section('title', __('theme_studio.edit_title').' · '.$theme->name)

@section('content')
@php
    $previewStyle = collect($lightCss)->map(fn ($value, $prop) => $prop.': '.$value)->implode('; ');
@endphp
<div class="sa-theme-studio animate-in">
    <nav class="mb-3 small" aria-label="{{ __('theme_studio.edit_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('theme_studio.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <a href="{{ route('superadmin.theme.index') }}" class="text-decoration-none text-muted-theme">{{ __('theme_studio.back_index') }}</a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ $theme->name }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('theme_studio.edit_title') }} · {{ $theme->name }}</h1>
        <p class="text-muted-theme mb-2">{{ __('theme_studio.edit_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('theme_studio.edit_help') }}</p>
    </header>

    <aside class="sa-callout sa-callout-info mb-3" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('theme_studio.gold_title') }}</strong>
            <p class="mb-0">{{ __('theme_studio.gold_body') }}</p>
        </div>
    </aside>
    <aside class="sa-callout sa-callout-info mb-4" role="note">
        <i class="bi bi-circle-half" aria-hidden="true"></i>
        <div>
            <strong>{{ __('theme_studio.system_title') }}</strong>
            <p class="mb-0">{{ __('theme_studio.system_body') }}</p>
        </div>
    </aside>

    <div class="d-flex flex-wrap gap-2 mb-4">
        @if(! $theme->is_active)
            <form method="POST" action="{{ route('superadmin.theme.activate', $theme) }}">
                @csrf
                <button class="btn btn-success btn-sm" onclick="return confirm(@json(__('theme_studio.confirm_activate')))">
                    {{ __('theme_studio.activate') }}
                </button>
            </form>
        @else
            <span class="sa-flag-state is-on align-self-center">{{ __('theme_studio.active_badge') }}</span>
        @endif
        <form method="POST" action="{{ route('superadmin.theme.duplicate', $theme) }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm">{{ __('theme_studio.duplicate') }}</button>
        </form>
        <form method="POST" action="{{ route('superadmin.theme.reset', $theme) }}">
            @csrf
            <button class="btn btn-outline-danger btn-sm" onclick="return confirm(@json(__('theme_studio.confirm_reset')))">
                {{ __('theme_studio.reset') }}
            </button>
        </form>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.theme.edit') }}">{{ __('theme_studio.open_staff') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.audit.index', ['action' => 'theme.']) }}">{{ __('theme_studio.open_audit') }}</a>
    </div>
    <p class="small text-muted-theme mb-4">{{ __('theme_studio.activate_help') }} {{ __('theme_studio.reset_help') }}</p>

    <section class="app-card p-3 mb-4" id="sa-theme-preview-wrap">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div>
                <h2 class="h6 page-title mb-1">{{ __('theme_studio.preview_title') }}</h2>
                <p class="small text-muted-theme mb-0">{{ __('theme_studio.preview_help') }}</p>
            </div>
            <div class="btn-group" role="group" aria-label="{{ __('theme_studio.preview_title') }}">
                <button type="button" class="btn btn-sm btn-outline-primary is-preview-mode" data-preview-mode="light" id="sa-preview-light">{{ __('theme_studio.preview_light') }}</button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-preview-mode="dark" id="sa-preview-dark">{{ __('theme_studio.preview_dark') }}</button>
            </div>
        </div>
        <div class="sa-theme-preview" id="sa-theme-preview" data-mode="light" style="{{ $previewStyle }}">
            <div class="sa-theme-preview-shell">
                <aside class="sa-theme-preview-nav" aria-label="{{ __('theme_studio.preview_nav') }}">
                    <div class="sa-theme-preview-brand">{{ $theme->site_name }}</div>
                    <span class="is-active">{{ __('theme_studio.preview_nav_home') }}</span>
                    <span>{{ __('theme_studio.preview_nav_learn') }}</span>
                </aside>
                <div class="sa-theme-preview-main">
                    <h3 class="sa-theme-preview-heading">{{ __('theme_studio.preview_title_sample') }}</h3>
                    <p class="sa-theme-preview-body">{{ __('theme_studio.preview_body') }}</p>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="sa-theme-preview-btn">{{ __('theme_studio.preview_button') }}</span>
                        <span class="sa-theme-preview-accent">{{ __('theme_studio.preview_accent') }}</span>
                    </div>
                    <div class="sa-theme-preview-alert">{{ __('theme_studio.preview_alert') }}</div>
                </div>
            </div>
        </div>
    </section>

    <form method="POST" action="{{ route('superadmin.theme.update', $theme) }}" id="theme-studio-form">
        @csrf
        @method('PUT')

        <section class="app-card p-3 mb-4" id="sa-theme-identity">
            <h2 class="h6 page-title mb-1">{{ __('theme_studio.identity_title') }}</h2>
            <p class="small text-muted-theme mb-3">{{ __('theme_studio.identity_help') }}</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="theme-name">{{ __('theme_studio.name_label') }}</label>
                    <input id="theme-name" name="name" class="form-control" value="{{ old('name', $theme->name) }}" required maxlength="100">
                    <p class="form-text mb-0">{{ __('theme_studio.name_help') }}</p>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="theme-site-name">{{ __('theme_studio.site_name_label') }}</label>
                    <input id="theme-site-name" name="site_name" class="form-control" value="{{ old('site_name', $theme->site_name) }}" required maxlength="150">
                    <p class="form-text mb-0">{{ __('theme_studio.site_name_help') }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="theme-logo-light">{{ __('theme_studio.logo_light') }}</label>
                    <input id="theme-logo-light" name="logo_light_url" class="form-control" value="{{ old('logo_light_url', $theme->logo_light_url) }}" maxlength="500">
                    <p class="form-text mb-0">{{ __('theme_studio.stored_path_help') }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="theme-logo-dark">{{ __('theme_studio.logo_dark') }}</label>
                    <input id="theme-logo-dark" name="logo_dark_url" class="form-control" value="{{ old('logo_dark_url', $theme->logo_dark_url) }}" maxlength="500">
                    <p class="form-text mb-0">{{ __('theme_studio.logo_dark_help') }}</p>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="theme-favicon">{{ __('theme_studio.favicon') }}</label>
                    <input id="theme-favicon" name="favicon_url" class="form-control" value="{{ old('favicon_url', $theme->favicon_url) }}" maxlength="500">
                    <p class="form-text mb-0">{{ __('theme_studio.favicon_help') }}</p>
                </div>
            </div>
        </section>

        <section class="app-card p-3 mb-4" id="sa-theme-tokens">
            <h2 class="h6 page-title mb-1">{{ __('theme_studio.tokens_title') }}</h2>
            <p class="small text-muted-theme mb-3">{{ __('theme_studio.tokens_help') }}</p>
            <div class="row g-4">
                @foreach(['light' => __('theme_studio.mode_light'), 'dark' => __('theme_studio.mode_dark')] as $mode => $modeLabel)
                    <div class="col-lg-6">
                        <h3 class="h6 text-muted-theme mb-1">{{ $modeLabel }}</h3>
                        <p class="form-text mb-3">{{ $mode === 'light' ? __('theme_studio.mode_light_help') : __('theme_studio.mode_dark_help') }}</p>
                        @foreach($groups as $group => $keys)
                            <fieldset class="sa-token-group mb-3">
                                <legend class="h6 mb-1">{{ __('theme_studio.group_'.$group) }}</legend>
                                <p class="form-text mb-2">{{ __('theme_studio.group_'.$group.'_help') }}</p>
                                @foreach($keys as $key)
                                    @php
                                        $value = old('tokens.'.$mode.'.'.$key, $tokens[$mode][$key] ?? '');
                                        $cssVar = $cssMap[$key] ?? '';
                                        $isColor = \App\Support\ThemeTokens::isColorToken($key);
                                        $fieldId = 'token-'.$mode.'-'.$key;
                                    @endphp
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $fieldId }}">{{ __('theme_studio.token_'.$key) }}</label>
                                        <div class="sa-token-inputs">
                                            @if($isColor)
                                                <input type="color"
                                                       class="form-control form-control-color sa-token-color"
                                                       value="{{ $value }}"
                                                       data-token-mode="{{ $mode }}"
                                                       data-token-key="{{ $key }}"
                                                       aria-label="{{ __('theme_studio.token_'.$key) }}">
                                            @endif
                                            <input id="{{ $fieldId }}"
                                                   type="text"
                                                   name="tokens[{{ $mode }}][{{ $key }}]"
                                                   class="form-control form-control-sm sa-token-text"
                                                   value="{{ $value }}"
                                                   maxlength="120"
                                                   data-token-mode="{{ $mode }}"
                                                   data-token-key="{{ $key }}">
                                        </div>
                                        <p class="form-text mb-0">
                                            {{ __('theme_studio.token_'.$key.'_help') }}
                                            · {{ __('theme_studio.token_key') }} <code>{{ $key }}</code>
                                            @if($cssVar)
                                                · {{ __('theme_studio.token_css') }} <code>{{ $cssVar }}</code>
                                            @endif
                                        </p>
                                    </div>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>

        <button class="btn btn-primary mb-2">{{ __('theme_studio.save') }}</button>
        <p class="form-text mb-4">{{ __('theme_studio.save_help') }}</p>
    </form>

    <section class="app-card p-3 mb-4" id="sa-theme-assets">
        <h2 class="h6 page-title mb-1">{{ __('theme_studio.assets_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('theme_studio.assets_help') }}</p>
        @foreach([
            'logo_light_url' => ['label' => __('theme_studio.logo_light'), 'help' => __('theme_studio.logo_light_help'), 'url' => $theme->resolvedLogoLightUrl()],
            'logo_dark_url' => ['label' => __('theme_studio.logo_dark'), 'help' => __('theme_studio.logo_dark_help'), 'url' => $theme->resolvedLogoDarkUrl()],
            'favicon_url' => ['label' => __('theme_studio.favicon'), 'help' => __('theme_studio.favicon_help'), 'url' => $theme->resolvedFaviconUrl()],
        ] as $field => $meta)
            <article class="sa-theme-asset mb-4">
                <h3 class="h6 mb-1">{{ $meta['label'] }}</h3>
                <p class="form-text mb-2">{{ $meta['help'] }}</p>
                @if($meta['url'])
                    <p class="small mb-2">
                        <span class="text-muted-theme">{{ __('theme_studio.current_logo') }}:</span>
                        <img src="{{ $meta['url'] }}" alt="" class="sa-theme-asset-img">
                    </p>
                @else
                    <p class="form-text mb-2">{{ __('theme_studio.no_logo') }}</p>
                @endif
                <p class="small mb-2">
                    {{ __('theme_studio.stored_path') }}:
                    <code>{{ $theme->{$field} ?: '—' }}</code>
                </p>
                <p class="form-text mb-2">{{ __('theme_studio.stored_path_help') }}</p>
                <form method="POST" action="{{ route('superadmin.theme.assets', $theme) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="field" value="{{ $field }}">
                    <div class="col-md-6">
                        <label class="form-label" for="asset-{{ $field }}">{{ __('theme_studio.upload') }}</label>
                        <input id="asset-{{ $field }}" type="file" name="file" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <p class="form-text mb-0">{{ __('theme_studio.upload_help') }}</p>
                    </div>
                    <div class="col-md-auto">
                        <button class="btn btn-outline-primary btn-sm">{{ __('theme_studio.upload_cta') }}</button>
                    </div>
                </form>
            </article>
        @endforeach
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const preview = document.getElementById('sa-theme-preview');
    const form = document.getElementById('theme-studio-form');
    if (!preview || !form) {
        return;
    }
    const cssMap = @json($cssMap);
    let mode = 'light';

    function applyPreview() {
        form.querySelectorAll('[data-token-mode="' + mode + '"].sa-token-text').forEach(function (el) {
            const key = el.getAttribute('data-token-key');
            const css = cssMap[key];
            if (css && el.value) {
                preview.style.setProperty(css, el.value);
            }
        });
        const bg1 = preview.style.getPropertyValue('--color-bg-1');
        const bg2 = preview.style.getPropertyValue('--color-bg-2');
        const bg3 = preview.style.getPropertyValue('--color-bg-3');
        if (bg1 && bg2 && bg3) {
            preview.style.setProperty('--gradient-bg', 'linear-gradient(160deg, ' + bg1 + ' 0%, ' + bg2 + ' 48%, ' + bg3 + ' 100%)');
        }
        preview.setAttribute('data-mode', mode);
        document.getElementById('sa-preview-light').classList.toggle('btn-primary', mode === 'light');
        document.getElementById('sa-preview-light').classList.toggle('btn-outline-primary', mode !== 'light');
        document.getElementById('sa-preview-dark').classList.toggle('btn-primary', mode === 'dark');
        document.getElementById('sa-preview-dark').classList.toggle('btn-outline-primary', mode !== 'dark');
    }

    form.addEventListener('input', function (event) {
        const target = event.target;
        if (!target.classList.contains('sa-token-text') && !target.classList.contains('sa-token-color')) {
            return;
        }
        if (target.classList.contains('sa-token-color')) {
            const key = target.getAttribute('data-token-key');
            const tokenMode = target.getAttribute('data-token-mode');
            const text = form.querySelector('.sa-token-text[data-token-mode="' + tokenMode + '"][data-token-key="' + key + '"]');
            if (text) {
                text.value = target.value;
            }
        }
        if (target.classList.contains('sa-token-text') && target.getAttribute('data-token-key')) {
            const color = form.querySelector('.sa-token-color[data-token-mode="' + target.getAttribute('data-token-mode') + '"][data-token-key="' + target.getAttribute('data-token-key') + '"]');
            if (color && /^#[0-9A-Fa-f]{6}$/.test(target.value)) {
                color.value = target.value;
            }
        }
        if (target.getAttribute('data-token-mode') === mode) {
            applyPreview();
        }
    });

    document.querySelectorAll('[data-preview-mode]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            mode = btn.getAttribute('data-preview-mode');
            applyPreview();
        });
    });
})();
</script>
@endpush
