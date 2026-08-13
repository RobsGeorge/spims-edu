@extends('layouts.app')
@section('title', $item->title.' — '.$offering->course->code)
@section('content')
<div class="mb-3">
    <a href="{{ route('learn.week', [$offering, $activeWeek]) }}" class="btn btn-link px-0">{{ __('learn.week', ['number' => $activeWeek->number]) }}</a>
    <h1 class="spims-title mb-1">{{ $item->title }}</h1>
    <p class="text-muted-theme mb-0"><span class="badge text-bg-light">{{ $item->type->value }}</span></p>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@error('learn')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="row">
    @include('learn.partials.week-nav')
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                @if($item->type->value === 'VIDEO')
                    @if($item->vimeo_id)
                        <div class="ratio ratio-16x9 mb-3">
                            <iframe src="https://player.vimeo.com/video/{{ $item->vimeo_id }}" allowfullscreen allow="autoplay; fullscreen; picture-in-picture" title="{{ $item->title }}"></iframe>
                        </div>
                    @endif
                    @if($item->body)<div class="mb-0">{!! nl2br(e($item->body)) !!}</div>@endif
                @elseif($item->type->value === 'READING')
                    @if($item->body)<div class="mb-3">{!! nl2br(e($item->body)) !!}</div>@endif
                    @if($item->file_url)
                        <a class="btn btn-outline-primary" href="{{ $item->file_url }}" target="_blank" rel="noopener">{{ __('learn.reading_open') }}</a>
                        <div class="ratio ratio-4x3 mt-3">
                            <iframe src="{{ $item->file_url }}" title="{{ $item->title }}"></iframe>
                        </div>
                    @endif
                @elseif($item->type->value === 'TEXT')
                    <div class="mb-0">{!! nl2br(e($item->body)) !!}</div>
                @endif
            </div>
        </div>

        @if(in_array($item->type->value, ['VIDEO', 'READING', 'TEXT'], true))
            @if($completed)
                <span class="badge text-bg-success">{{ __('learn.already_complete') }}</span>
            @else
                <form method="POST" action="{{ route('learn.item.complete', [$offering, $item]) }}">
                    @csrf
                    <button class="btn btn-primary">{{ __('learn.mark_complete') }}</button>
                </form>
            @endif
        @endif
    </div>
</div>
@endsection
