@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()) && \Illuminate\Support\Facades\Route::has('superadmin.audit.index'))
    <a href="{{ $url ?? route('superadmin.audit.index') }}" class="audit-entrance-banner text-decoration-none mb-4">
        <span class="audit-entrance-icon" aria-hidden="true">
            <i class="bi bi-journal-text"></i>
        </span>
        <span class="min-w-0">
            <span class="audit-entrance-title">{{ __('audit.entrance_title') }}</span>
            <span class="audit-entrance-body">{{ $caption ?? __('audit.entrance_body') }}</span>
        </span>
        <span class="audit-entrance-cta">{{ __('audit.entrance_cta') }}</span>
    </a>
@endif
