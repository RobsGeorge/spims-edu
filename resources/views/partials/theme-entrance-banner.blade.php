@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.theme.index'))
    <a href="{{ $url ?? route('superadmin.theme.index') }}" class="theme-entrance-banner text-decoration-none mb-4">
        <span class="theme-entrance-icon" aria-hidden="true">
            <i class="bi bi-palette"></i>
        </span>
        <span class="min-w-0">
            <span class="theme-entrance-title">{{ __('theme_studio.entrance_title') }}</span>
            <span class="theme-entrance-body">{{ $caption ?? __('theme_studio.entrance_body') }}</span>
        </span>
        <span class="theme-entrance-cta">{{ __('theme_studio.entrance_cta') }}</span>
    </a>
@endif
