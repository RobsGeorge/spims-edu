@if(!empty($studentPreview))
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>{{ __('offerings.preview_banner') }}</span>
        <form method="POST" action="{{ route('offerings.preview.stop', $offering) }}" class="mb-0">
            @csrf
            <button class="btn btn-sm btn-outline-dark">{{ __('offerings.preview_exit') }}</button>
        </form>
    </div>
@endif
