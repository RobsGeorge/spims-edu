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
            @if($weeks->isEmpty())
                <div class="alert alert-info academic-alert mb-0">{{ __('learn.no_weeks') }}</div>
            @else
                <div class="accordion" id="weekAccordion">
                    @foreach($weeks as $week)
                        @php
                            $unlocked = $enrollment
                                ? $progress->isWeekUnlocked($enrollment, $offering, $week)
                                : app(\App\Services\Offerings\ContentGatingService::class)
                                    ->isWeekUnlocked($offering, $week, enrolled: true, completedWeekNumbers: $completedWeekNumbers ?? []);
                            $done = $enrollment ? $progress->isWeekComplete($enrollment, $week) : false;
                            $isActive = $activeWeek && $activeWeek->id === $week->id;
                            $weekItemIds = $week->items->pluck('id')->toArray();
                            $weekTotal = count($weekItemIds);
                            $weekCompleted = count(array_intersect($weekItemIds, $completedItemIds));
                        @endphp
                        <div class="accordion-item mb-2 border rounded-3 {{ $done ? 'border-success' : ($isActive ? 'border-primary' : '') }}">
                            <h2 class="accordion-header">
                                <button class="accordion-button rounded-3 {{ $isActive ? '' : 'collapsed' }}"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#week-{{ $week->id }}"
                                        aria-expanded="{{ $isActive ? 'true' : 'false' }}"
                                        aria-controls="week-{{ $week->id }}"
                                        @if(!$unlocked) aria-disabled="true" style="cursor:default;" @endif>
                                    <span class="d-flex align-items-center gap-2 w-100 flex-wrap">
                                        <span class="fw-semibold">
                                            {{ __('learn.week', ['number' => $week->number]) }}: {{ $week->title }}
                                        </span>
                                        <span class="ms-auto d-flex align-items-center gap-2 flex-shrink-0">
                                            @if($done)
                                                <span class="badge text-bg-success"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>{{ __('learn.completed') }}</span>
                                            @elseif(!$unlocked)
                                                <span class="badge text-bg-info">{{ __('learn.locked') }}</span>
                                            @else
                                                <span class="badge text-bg-info">{{ $weekCompleted }}/{{ $weekTotal }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </button>
                            </h2>
                            <div id="week-{{ $week->id }}" class="accordion-collapse collapse {{ $isActive ? 'show' : '' }}">
                                <div class="accordion-body p-0">
                                    @if(!$unlocked)
                                        <div class="alert alert-warning academic-alert m-3 mb-3">
                                            @if($offering->mode->value === 'COHORT' && $week->unlock_date)
                                                {{ __('learn.unlock_on_date', ['date' => $week->unlock_date->format('d M Y')]) }}
                                            @else
                                                {{ __('learn.unlock_after_prior') }}
                                            @endif
                                        </div>
                                    @elseif($week->items->isEmpty())
                                        <p class="px-3 py-3 spims-text-dim mb-0 small">{{ __('learn.no_items') }}</p>
                                    @else
                                        <ul class="list-group list-group-flush">
                                            @foreach($week->items as $item)
                                                @php $itemDone = in_array($item->id, $completedItemIds, true); @endphp
                                                <li class="list-group-item px-3 py-2 d-flex align-items-center gap-2">
                                                    <a href="{{ route('learn.item', [$offering, $item]) }}"
                                                       class="flex-grow-1 text-decoration-none text-body d-flex align-items-center gap-2">
                                                        <x-badge :value="$item->type" class="flex-shrink-0" />
                                                        <span>{{ $item->title }}</span>
                                                    </a>
                                                    @if($itemDone)
                                                        <span class="badge text-bg-success flex-shrink-0">
                                                            <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>{{ __('learn.done') }}
                                                        </span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="p-3 pt-2">
                                            <a href="{{ route('learn.week', [$offering, $week]) }}" class="btn btn-primary btn-sm">
                                                {{ __('learn.open_week') }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
