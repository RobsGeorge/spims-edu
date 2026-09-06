@props(['payload'])

<section class="app-card p-3 mt-3" data-event-check-in>
    <h2 class="h6 mb-1">{{ __('events.check_in_title') }}</h2>
    <p class="small text-muted-theme mb-3">{{ __('events.check_in_help') }}</p>
    <div class="spims-event-ticket border rounded-3 p-3 text-center">
        <div class="small text-muted-theme mb-2">{{ __('events.check_in_code') }}</div>
        <code class="spims-event-qr d-block user-select-all" dir="ltr" lang="en">{{ $payload }}</code>
    </div>
</section>
