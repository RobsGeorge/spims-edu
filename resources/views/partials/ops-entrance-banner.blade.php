@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.ops'))
    <a href="{{ $url ?? route('superadmin.ops') }}" class="ops-entrance-banner text-decoration-none mb-4">
        <span class="ops-entrance-icon" aria-hidden="true">
            <i class="bi bi-hdd-network"></i>
        </span>
        <span class="min-w-0">
            <span class="ops-entrance-kicker">{{ __('ops.nav_ops') }}</span>
            <span class="ops-entrance-title">{{ __('ops.entrance_title') }}</span>
            <span class="ops-entrance-body">{{ $caption ?? __('ops.entrance_body') }}</span>
        </span>
        <span class="ops-entrance-cta">{{ __('ops.entrance_cta') }}</span>
    </a>
@endif
