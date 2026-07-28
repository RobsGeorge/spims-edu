@extends('layouts.app')
@section('title', __('offerings.public_preview'))
@section('content')
<h1 class="spims-title">{{ $preview['course']['code'] }} — {{ $preview['course']['title'] }}</h1>
<p class="text-muted-theme">{{ __('offerings.mode') }}: {{ $preview['mode'] }}</p>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6">{{ __('offerings.all_week_titles') }}</h2>
        <ol class="mb-0">
            @foreach($preview['week_titles'] as $week)
                <li>Week {{ $week['number'] }}: {{ $week['title'] }}</li>
            @endforeach
        </ol>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h6">{{ __('offerings.week_one_content') }}</h2>
        @forelse($preview['week_one'] as $item)
            <div class="mb-2">
                <strong>{{ $item['type'] }}:</strong> {{ $item['title'] }}
                @if($item['vimeo_id'])<div class="small">Vimeo: {{ $item['vimeo_id'] }}</div>@endif
                @if($item['body'])<div class="small">{{ $item['body'] }}</div>@endif
            </div>
        @empty
            <p class="text-muted-theme mb-0">{{ __('offerings.no_week_one') }}</p>
        @endforelse
    </div>
</div>
@endsection
