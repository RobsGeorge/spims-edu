@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.integrations'))
    <a href="{{ $url ?? route('superadmin.integrations') }}" class="integrations-entrance-banner text-decoration-none mb-4">
        <span class="integrations-entrance-icon" aria-hidden="true">
            <i class="bi bi-plug"></i>
        </span>
        <span class="min-w-0">
            <span class="integrations-entrance-kicker">{{ __('integrations.nav_integrations') }}</span>
            <span class="integrations-entrance-title">{{ __('integrations.entrance_title') }}</span>
            <span class="integrations-entrance-body">{{ $caption ?? __('integrations.entrance_body') }}</span>
        </span>
        <span class="integrations-entrance-cta">{{ __('integrations.entrance_cta') }}</span>
    </a>
@endif
