@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.config'))
    <a href="{{ $url ?? route('superadmin.config') }}" class="config-entrance-banner text-decoration-none mb-4">
        <span class="config-entrance-icon" aria-hidden="true">
            <i class="bi bi-sliders2"></i>
        </span>
        <span class="min-w-0">
            <span class="config-entrance-title">{{ __('system_settings.entrance_title') }}</span>
            <span class="config-entrance-body">{{ $caption ?? __('system_settings.entrance_body') }}</span>
        </span>
        <span class="config-entrance-cta">{{ __('system_settings.entrance_cta') }}</span>
    </a>
@endif
