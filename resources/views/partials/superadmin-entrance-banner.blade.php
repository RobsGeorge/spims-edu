@if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()))
    <a href="{{ route('superadmin.index') }}" class="sa-entrance-banner text-decoration-none mb-4">
        <span class="sa-entrance-icon" aria-hidden="true">
            <i class="bi bi-shield-lock-fill"></i>
        </span>
        <span class="min-w-0">
            <span class="sa-entrance-title">{{ __('superadmin.entrance_title') }}</span>
            <span class="sa-entrance-body">{{ $caption ?? __('superadmin.entrance_body') }}</span>
        </span>
        <span class="sa-entrance-cta">{{ __('superadmin.entrance_cta') }}</span>
    </a>
@endif
