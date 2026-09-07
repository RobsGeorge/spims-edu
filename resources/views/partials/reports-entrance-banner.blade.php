@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.reports'))
    <a href="{{ $url ?? route('superadmin.reports') }}" class="reports-entrance-banner text-decoration-none mb-4">
        <span class="reports-entrance-icon" aria-hidden="true">
            <i class="bi bi-bar-chart-line"></i>
        </span>
        <span class="min-w-0">
            <span class="reports-entrance-kicker">{{ __('school_reports.nav_reports') }}</span>
            <span class="reports-entrance-title">{{ __('school_reports.entrance_title') }}</span>
            <span class="reports-entrance-body">{{ $caption ?? __('school_reports.entrance_body') }}</span>
        </span>
        <span class="reports-entrance-cta">{{ __('school_reports.entrance_cta') }}</span>
    </a>
@endif
