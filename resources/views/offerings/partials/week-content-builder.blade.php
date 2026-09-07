@php
    use App\Enums\ContentItemType;
    $contentTypes = $contentTypes ?? ContentItemType::cases();
@endphp
@foreach($offering->weeks->sortBy('number') as $week)
    <div class="border rounded-3 p-3 mb-3" data-week-id="{{ $week->id }}">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div>
                <h3 class="h6 mb-1">{{ __('teach.week_n', ['n' => $week->number]) }} — {{ $week->title }}</h3>
                <p class="small text-muted-theme mb-0">{{ $week->items->count() }} {{ __('teach.items') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.weeks.items', $week) }}" enctype="multipart/form-data" class="row g-2 mb-3">
            @csrf
            <div class="col-md-2">
                <label class="form-label small mb-1">{{ __('offerings.item_type') }}</label>
                <select name="type" class="form-select form-select-sm" required>
                    @foreach($contentTypes as $type)
                        <option value="{{ $type->value }}">{{ __('learning.item_'.strtolower($type->value)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('academics.title') }}</label>
                <input name="title" class="form-control form-control-sm" placeholder="{{ __('academics.title') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('offerings.video_url') }}</label>
                <input name="video_url" class="form-control form-control-sm" placeholder="{{ __('offerings.video_url_ph') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">{{ __('offerings.vimeo_id') }}</label>
                <input name="vimeo_id" class="form-control form-control-sm" placeholder="{{ __('offerings.vimeo_id') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">{{ __('offerings.external_url') }}</label>
                <input name="file_url" class="form-control form-control-sm" placeholder="{{ __('offerings.external_url_ph') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">{{ __('offerings.upload_file') }}</label>
                <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,application/pdf,image/*">
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">{{ __('offerings.text_body') }}</label>
                <textarea name="body" class="form-control form-control-sm" rows="2" placeholder="{{ __('offerings.text_body') }}"></textarea>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-sm btn-primary w-100">{{ __('offerings.add_item') }}</button>
            </div>
            <div class="col-12">
                <p class="small text-muted-theme mb-0">{{ __('offerings.item_starts_draft') }}</p>
            </div>
        </form>

        @forelse($week->items->sortBy('order') as $item)
            <article class="border rounded-3 p-2 mb-2" data-item-id="{{ $item->id }}">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div>
                        <span class="badge text-bg-light">{{ __('learning.item_'.strtolower($item->type->value)) }}</span>
                        <strong>{{ $item->title }}</strong>
                        @if($item->isPublished())
                            <x-status-badge status="success" :label="__('offerings.status_published')" />
                        @else
                            <x-status-badge status="warning" :label="__('offerings.status_draft')" />
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <form method="POST" action="{{ route('admin.content-items.move-up', $item) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">{{ __('offerings.move_up') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.content-items.move-down', $item) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">{{ __('offerings.move_down') }}</button>
                        </form>
                        @if($offering->weeks->count() > 1)
                            <form method="POST" action="{{ route('admin.content-items.move', $item) }}" class="d-flex gap-1">
                                @csrf
                                <select name="week_id" class="form-select form-select-sm" aria-label="{{ __('offerings.move_to_week') }}">
                                    @foreach($offering->weeks->sortBy('number') as $targetWeek)
                                        <option value="{{ $targetWeek->id }}" @selected($targetWeek->id === $week->id)>{{ __('teach.week_n', ['n' => $targetWeek->number]) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-secondary">{{ __('offerings.move_to_week') }}</button>
                            </form>
                        @endif
                        @if($item->isPublished())
                            <form method="POST" action="{{ route('admin.content-items.unpublish', $item) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary">{{ __('offerings.unpublish') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.content-items.publish', $item) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary">{{ __('offerings.publish') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.content-items.destroy', $item) }}" onsubmit="return confirm(@json(__('offerings.delete_item_confirm')))">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('offerings.delete_item') }}</button>
                        </form>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.content-items.update', $item) }}" enctype="multipart/form-data" class="row g-2">
                    @csrf
                    @method('PUT')
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm" required>
                            @foreach($contentTypes as $type)
                                <option value="{{ $type->value }}" @selected($item->type === $type)>{{ __('learning.item_'.strtolower($type->value)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input name="title" class="form-control form-control-sm" value="{{ $item->title }}" required>
                    </div>
                    <div class="col-md-3">
                        <input name="vimeo_id" class="form-control form-control-sm" value="{{ $item->vimeo_id }}" placeholder="{{ __('offerings.video_url_ph') }}">
                    </div>
                    <div class="col-md-4">
                        <input name="file_url" class="form-control form-control-sm" value="{{ $item->file_url }}" placeholder="{{ __('offerings.external_url_ph') }}">
                    </div>
                    <div class="col-md-4">
                        <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,application/pdf,image/*">
                    </div>
                    <div class="col-md-6">
                        <textarea name="body" class="form-control form-control-sm" rows="2">{{ $item->body }}</textarea>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-sm btn-outline-primary w-100">{{ __('ui.save_changes') }}</button>
                    </div>
                </form>
                @if($item->type->value === 'VIDEO' && $item->videoIframeUrl())
                    <div class="ratio ratio-16x9 mt-2">
                        <iframe src="{{ $item->videoIframeUrl() }}" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" allowfullscreen allow="autoplay; fullscreen; picture-in-picture" title="{{ $item->title }}"></iframe>
                    </div>
                @elseif($item->isStoredFile() || $item->remoteReading())
                    <div class="mt-2">
                        @include('learn.partials.item-media', ['item' => $item])
                    </div>
                @endif
            </article>
        @empty
            <p class="small text-muted-theme mb-0">{{ __('offerings.no_items_in_week') }}</p>
        @endforelse
    </div>
@endforeach
