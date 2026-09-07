@if(!empty($impersonator) && auth()->check())
    <div class="sa-impersonation-banner" role="alert">
        <i class="bi bi-incognito" aria-hidden="true"></i>
        <div class="sa-impersonation-copy min-w-0">
            <strong>{{ __('people.impersonating_title', ['name' => auth()->user()->displayName()]) }}</strong>
            <p class="mb-0">{{ __('people.impersonating_help') }}</p>
        </div>
        <form method="POST" action="{{ route('impersonation.stop') }}" class="sa-impersonation-stop">
            @csrf
            <button type="submit" class="btn btn-sm btn-light">
                {{ __('people.impersonation_stop') }}
            </button>
        </form>
    </div>
@endif
