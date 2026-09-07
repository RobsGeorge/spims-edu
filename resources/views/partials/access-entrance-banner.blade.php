@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.access'))
    <a href="{{ $url ?? route('superadmin.access') }}" class="access-entrance-banner text-decoration-none mb-4">
        <span class="access-entrance-icon" aria-hidden="true">
            <i class="bi bi-diagram-3"></i>
        </span>
        <span class="min-w-0">
            <span class="access-entrance-kicker">{{ __('access.nav_access') }}</span>
            <span class="access-entrance-title">{{ __('access.entrance_title') }}</span>
            <span class="access-entrance-body">{{ $caption ?? __('access.entrance_body') }}</span>
        </span>
        <span class="access-entrance-cta">{{ __('access.entrance_cta') }}</span>
    </a>
@endif
