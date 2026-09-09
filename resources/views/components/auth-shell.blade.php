@props([
    'title',
    'lead' => null,
])

@php
    $theme = view()->shared('activeTheme');
    $siteName = $theme?->site_name ?? __('ui.home_heading');
@endphp

<div {{ $attributes->merge(['class' => 'auth-split animate-in']) }}>
    <div class="auth-split__panel auth-card">
        <aside class="auth-split__brand d-none d-md-flex" aria-hidden="false">
            <div class="auth-split__brand-motif" aria-hidden="true"></div>
            <div class="auth-split__brand-top">
                <p class="auth-split__brand-name mb-1">{{ $siteName }}</p>
                <p class="auth-split__brand-tagline mb-0">{{ __('ui.auth_brand_tagline') }}</p>
            </div>
            <div class="auth-split__brand-bottom">
                <p class="auth-split__brand-welcome mb-3">{{ __('ui.auth_brand_welcome') }}</p>
                <span class="auth-split__brand-rule" aria-hidden="true"></span>
            </div>
        </aside>

        <div class="auth-split__form">
            <div class="auth-split__mobile-brand d-md-none text-center mb-4">
                <p class="auth-split__mobile-name mb-1">{{ $siteName }}</p>
                <p class="text-muted-theme mb-0">{{ __('ui.auth_brand_welcome') }}</p>
            </div>
            <div class="auth-split__form-inner">
                <h1 class="auth-split__title spims-title mb-2">{{ $title }}</h1>
                @if($lead)
                    <p class="text-muted-theme mb-4">{{ $lead }}</p>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
