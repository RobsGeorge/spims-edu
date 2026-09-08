@extends('layouts.app')
@section('title', __('learn.week', ['number' => $activeWeek->number]).' — '.$offering->course->code)
@section('content')
<div class="mb-3">
    <a href="{{ route('learn.offering', $offering) }}" class="btn btn-link px-0">{{ __('learn.back_to_course') }}</a>
    <h1 class="spims-title mb-1">{{ __('learn.week', ['number' => $activeWeek->number]) }}: {{ $activeWeek->title }}</h1>
    <p class="spims-text-dim mb-0">{{ $offering->course->code }}</p>
</div>
@include('offerings.partials.student-preview-banner')
@error('learn')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="row">
    @include('learn.partials.week-nav')
    <div class="col-lg-9">
        <x-card variant="panel">
            @if(! $unlocked)
                <div class="alert alert-warning mb-0">
                    <strong>{{ __('learn.week_locked') }}</strong>
                    @if($offering->mode->value === 'COHORT' && $activeWeek->unlock_date)
                        <div>{{ __('learn.unlock_on_date', ['date' => $activeWeek->unlock_date->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}</div>
                    @else
                        <div>{{ __('learn.unlock_after_prior') }}</div>
                    @endif
                </div>
            @else
                <h2 class="h6">{{ __('learn.items') }}</h2>
                <div class="list-group list-group-flush">
                    @forelse($activeWeek->items as $item)
                        @php $done = in_array($item->id, $completedItemIds, true); @endphp
                        <a href="{{ route('learn.item', [$offering, $item]) }}" class="list-group-item list-group-item-action px-0 d-flex justify-content-between align-items-center">
                            <span>
                                <x-badge :value="$item->type" class="me-1" />
                                {{ $item->title }}
                            </span>
                            @if($done)<span class="badge text-bg-success">{{ __('learn.completed') }}</span>@endif
                        </a>
                    @empty
                        <p class="spims-text-dim mb-0">{{ __('learn.no_items') }}</p>
                    @endforelse
                </div>
            @endif
        </x-card>
    </div>
</div>
@endsection
