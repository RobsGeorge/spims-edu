@extends('layouts.app')
@section('title', $item->title.' — '.$offering->course->code)
@section('content')
<div class="mb-3">
    <a href="{{ route('learn.week', [$offering, $activeWeek]) }}" class="btn btn-link px-0">{{ __('learn.week', ['number' => $activeWeek->number]) }}</a>
    <h1 class="spims-title mb-1">{{ $item->title }}</h1>
    <p class="spims-text-dim mb-0"><x-badge :value="$item->type" /></p>
</div>
@include('offerings.partials.student-preview-banner')
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@error('learn')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="row">
    @include('learn.partials.week-nav')
    <div class="col-lg-9">
        <x-card variant="panel" class="mb-3">
            @include('learn.partials.item-media', ['item' => $item])
        </x-card>

        @if(empty($studentPreview) && in_array($item->type->value, ['VIDEO', 'READING', 'TEXT', 'FILE'], true))
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
