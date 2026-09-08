@extends('layouts.app')
@section('title', __('offerings.public_preview'))
@section('content')
<h1 class="spims-title">{{ $preview['course']['code'] }} — {{ $preview['course']['title'] }}</h1>
<p class="spims-text-dim">{{ __('offerings.mode') }}: {{ $preview['mode'] }}</p>

<x-card variant="panel" class="mb-3">
    <h2 class="h6">{{ __('offerings.all_week_titles') }}</h2>
    <ol class="mb-0">
        @foreach($preview['week_titles'] as $week)
            <li>Week {{ $week['number'] }}: {{ $week['title'] }}</li>
        @endforeach
    </ol>
</x-card>

<x-card variant="panel">
    <h2 class="h6">{{ __('offerings.week_one_content') }}</h2>
    @forelse($weekOneItems as $item)
        <article class="mb-4">
            <h3 class="h6 mb-2">
                <span class="badge text-bg-info">{{ __('learning.item_'.strtolower($item->type->value)) }}</span>
                {{ $item->title }}
            </h3>
            @include('learn.partials.item-media', [
                'item' => $item,
                'storedFileUrl' => $item->isStoredFile() ? route('offerings.preview.item.file', [$offering, $item]) : null,
                'hideDownload' => true,
            ])
        </article>
    @empty
        <p class="spims-text-dim mb-0">{{ __('offerings.no_week_one') }}</p>
    @endforelse
</x-card>
@endsection
