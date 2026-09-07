@if(auth()->check() && app(\App\Support\AuthorizeService::class)->allows(auth()->user(), 'users.manage'))
    <a href="{{ route('admin.users.index') }}" class="people-entrance-banner text-decoration-none mb-4">
        <span class="people-entrance-icon" aria-hidden="true">
            <i class="bi bi-people-fill"></i>
        </span>
        <span class="min-w-0">
            <span class="people-entrance-title">{{ __('people.entrance_title') }}</span>
            <span class="people-entrance-body">{{ $caption ?? __('people.entrance_body') }}</span>
        </span>
        <span class="people-entrance-cta">{{ __('people.entrance_cta') }}</span>
    </a>
@endif
