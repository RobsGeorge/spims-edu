@php
    use App\Enums\ContentItemType;
    $type = $item->type instanceof ContentItemType ? $item->type->value : (string) $item->type;
    $canDownload = (bool) config('spims.content.student_file_download', true);
    $remote = $item->remoteReading();
@endphp
@if($type === 'VIDEO' && $item->videoIframeUrl())
    <div class="ratio ratio-16x9 mb-3 player-video">
        <iframe src="{{ $item->videoIframeUrl() }}" allowfullscreen allow="autoplay; fullscreen; picture-in-picture" title="{{ $item->title }}"></iframe>
    </div>
    @if($item->body)<div class="mb-0">{!! nl2br(e($item->body)) !!}</div>@endif
@elseif(in_array($type, ['READING', 'FILE'], true))
    @if($item->body)<div class="mb-3">{!! nl2br(e($item->body)) !!}</div>@endif

    @if($item->isStoredFile())
        @if($item->isStoredPdf())
            <div class="ratio ratio-4x3 mb-3">
                <iframe src="{{ route('learn.item.file', $item) }}" title="{{ $item->title }}"></iframe>
            </div>
        @elseif($item->isStoredImage())
            <img src="{{ route('learn.item.file', $item) }}" alt="{{ $item->title }}" class="img-fluid rounded mb-3">
        @endif
        @if($canDownload)
            <a class="btn btn-outline-primary" href="{{ route('learn.item.file', ['item' => $item, 'download' => 1]) }}">{{ __('learn.file_download') }}</a>
        @endif
    @elseif($remote)
        @if($remote->embeddable)
            <div class="ratio ratio-4x3 mb-3">
                <iframe src="{{ $remote->canonicalUrl }}" title="{{ $item->title }}"></iframe>
            </div>
            <a class="btn btn-outline-primary" href="{{ $remote->canonicalUrl }}" target="_blank" rel="noopener">{{ __('learn.reading_open') }}</a>
        @else
            <p class="text-muted-theme">{{ __('learn.reading_link_only') }}</p>
            <a class="btn btn-outline-primary" href="{{ $remote->canonicalUrl }}" target="_blank" rel="noopener">{{ __('learn.reading_open') }}</a>
        @endif
    @endif
@elseif($type === 'TEXT')
    <div class="mb-0">{!! nl2br(e($item->body)) !!}</div>
@endif
