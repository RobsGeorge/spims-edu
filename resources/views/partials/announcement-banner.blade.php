@if(!empty($activeBanner))
    <div class="alert alert-primary d-flex justify-content-between align-items-start gap-3 mb-3" role="status">
        <div>
            <div class="small text-uppercase">{{ __('communications.banner_label') }}</div>
            <a href="{{ route('announcements.show', $activeBanner) }}" class="fw-semibold">{{ $activeBanner->title }}</a>
            <div class="small">{{ \Illuminate\Support\Str::limit($activeBanner->localizedBody(), 140) }}</div>
        </div>
        <form method="POST" action="{{ route('announcements.dismiss-banner', $activeBanner) }}">
            @csrf
            <button class="btn btn-sm btn-outline-primary">{{ __('communications.dismiss_banner') }}</button>
        </form>
    </div>
@endif
