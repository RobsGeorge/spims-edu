@php
    use App\Enums\ContentItemType;
    $type = $item->type instanceof ContentItemType ? $item->type->value : (string) $item->type;
@endphp
@if($type === 'VIDEO' && $item->videoIframeUrl())
    <div class="ratio ratio-16x9 mb-3 player-video">
        <iframe src="{{ $item->videoIframeUrl() }}" allowfullscreen allow="autoplay; fullscreen; picture-in-picture" title="{{ $item->title }}"></iframe>
    </div>
    @if($item->body)<div class="mb-0">{!! nl2br(e($item->body)) !!}</div>@endif
@elseif($type === 'READING')
    @if($item->body)<div class="mb-3">{!! nl2br(e($item->body)) !!}</div>@endif
    @if($item->file_url)
        <a class="btn btn-outline-primary" href="{{ $item->file_url }}" target="_blank" rel="noopener">{{ __('learn.reading_open') }}</a>
        <div class="ratio ratio-4x3 mt-3">
            <iframe src="{{ $item->file_url }}" title="{{ $item->title }}"></iframe>
        </div>
    @endif
@elseif($type === 'TEXT')
    <div class="mb-0">{!! nl2br(e($item->body)) !!}</div>
@elseif($type === 'FILE' && $item->file_url)
    <a class="btn btn-outline-primary" href="{{ $item->file_url }}" target="_blank" rel="noopener">{{ __('learn.reading_open') }}</a>
@endif
