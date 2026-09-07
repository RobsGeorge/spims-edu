@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.features'))
    <a href="{{ $url ?? route('superadmin.features') }}" class="features-entrance-banner text-decoration-none mb-4">
        <span class="features-entrance-icon" aria-hidden="true">
            <i class="bi bi-toggles"></i>
        </span>
        <span class="min-w-0">
            <span class="features-entrance-title">{{ __('features.entrance_title') }}</span>
            <span class="features-entrance-body">{{ $caption ?? __('features.entrance_body') }}</span>
        </span>
        <span class="features-entrance-cta">{{ __('features.entrance_cta') }}</span>
    </a>
@endif
