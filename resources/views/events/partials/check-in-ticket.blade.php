@props(['payload'])

<x-card variant="quiet" tag="section" class="mt-3" data-event-check-in>
    <h2 class="h6 mb-1 spims-title">{{ __('events.check_in_title') }}</h2>
    <p class="small spims-text-dim mb-3">{{ __('events.check_in_help') }}</p>
    <div class="spims-event-ticket border rounded p-3 text-center">
        <div class="small spims-text-dim mb-2">{{ __('events.check_in_code') }}</div>
        <code class="spims-event-qr d-block user-select-all" dir="ltr" lang="en" style="word-break: break-all">{{ $payload }}</code>
    </div>
</x-card>
