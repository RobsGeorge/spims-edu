@extends('layouts.app')
@section('title', __('learn.player_title').' — '.$offering->course->code)
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="spims-title mb-1">{{ $offering->course->code }} — {{ $offering->course->title }}</h1>
        @php $offeringModeVal = $offering->mode->value; @endphp
        <p class="spims-text-dim mb-1">{{ __('offerings.mode') }}: {{ $offeringModeVal }}</p>
        <div class="progress mb-2" style="height:6px" role="progressbar"
             aria-valuenow="{{ (int)($enrollment->progress_percent ?? 0) }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-primary" style="width:{{ (int)($enrollment->progress_percent ?? 0) }}%"></div>
        </div>
        <p class="small spims-text-dim mb-0">{{ (int)($enrollment->progress_percent ?? 0) }}% {{ __('learn.complete') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(!empty($hasPublishedProjects))
            <a href="{{ route('student.projects.index', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('projects.nav') }}</a>
        @endif
        <a href="{{ route('student.surveys.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('learning.surveys') }}</a>
        <a href="{{ route('offerings.completion', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('completion.nav') }}</a>
        <a href="{{ route('enrollments.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('ui.nav_enrollments') }}</a>
    </div>
</div>
@include('offerings.partials.student-preview-banner')
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@error('learn')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="row">
    @include('learn.partials.week-nav')
    <div class="col-lg-9">
        @if($activeWeek)
            <x-card variant="panel">
                <h2 class="h5">{{ __('learn.week', ['number' => $activeWeek->number]) }}: {{ $activeWeek->title }}</h2>
                <p class="spims-text-dim">{{ __('learn.items') }}</p>
                <ul class="list-group list-group-flush">
                    @forelse($activeWeek->items as $item)
                        @php $done = in_array($item->id, $completedItemIds, true); @endphp
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <a href="{{ route('learn.item', [$offering, $item]) }}">
                                <x-badge :value="$item->type" class="me-1" />
                                {{ $item->title }}
                            </a>
                            @if($done)<span class="badge text-bg-success">{{ __('learn.completed') }}</span>@endif
                        </li>
                    @empty
                        <li class="list-group-item px-0 spims-text-dim">{{ __('learn.no_items') }}</li>
                    @endforelse
                </ul>
                <a class="btn btn-primary mt-3" href="{{ route('learn.week', [$offering, $activeWeek]) }}">{{ __('learn.week', ['number' => $activeWeek->number]) }}</a>
            </x-card>
        @else
            <div class="alert alert-info mb-0">{{ __('learn.no_weeks') }}</div>
        @endif
    </div>
</div>
@endsection
