@extends('layouts.app')
@section('title', __('learn.player_title').' — '.$offering->course->code)
@section('content')
<div class="learn-shell animate-in">
    <x-page-header
        :title="$offering->course->code.' — '.$offering->course->title"
        :subtitle="__('offerings.mode').': '.$offering->mode->value.' · '.__('learning.progress', ['percent' => (int)($enrollment->progress_percent ?? 0)])"
    >
        <x-slot:actions>
            @if(!empty($hasPublishedProjects))
                <a href="{{ route('student.projects.index', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('projects.nav') }}</a>
            @endif
            <a href="{{ route('student.surveys.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('learning.surveys') }}</a>
            <a href="{{ route('offerings.completion', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('completion.nav') }}</a>
            <a href="{{ route('enrollments.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('ui.nav_enrollments') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="progress mb-3" style="height:6px" role="progressbar"
         aria-valuenow="{{ (int)($enrollment->progress_percent ?? 0) }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar bg-primary" style="width:{{ (int)($enrollment->progress_percent ?? 0) }}%"></div>
    </div>

    @include('offerings.partials.student-preview-banner')
    @if(session('status'))<div class="alert alert-success academic-alert">{{ session('status') }}</div>@endif
    @error('learn')<div class="alert alert-danger academic-alert">{{ $message }}</div>@enderror

    <div class="row">
        @include('learn.partials.week-nav')
        <div class="col-lg-9">
            @if($activeWeek)
                <x-card variant="panel">
                    <h2 class="h5 spims-title">{{ __('learn.week', ['number' => $activeWeek->number]) }}: {{ $activeWeek->title }}</h2>
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
                <div class="alert alert-info academic-alert mb-0">{{ __('learn.no_weeks') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
