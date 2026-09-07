@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.status'))
    <a href="{{ $url ?? route('superadmin.status') }}" class="status-entrance-banner text-decoration-none mb-4">
        <span class="status-entrance-icon" aria-hidden="true">
            <i class="bi bi-clipboard2-pulse"></i>
        </span>
        <span class="min-w-0">
            <span class="status-entrance-kicker">{{ __('status.nav_status') }}</span>
            <span class="status-entrance-title">{{ __('status.entrance_title') }}</span>
            <span class="status-entrance-body">{{ $caption ?? __('status.entrance_body') }}</span>
        </span>
        <span class="status-entrance-cta">{{ __('status.entrance_cta') }}</span>
    </a>
@endif
