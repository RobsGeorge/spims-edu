@extends('layouts.app')
@section('title', __('assessment.assessments'))
@section('content')

<x-page-header
    :title="$attempt->student->email . ' — #' . $attempt->attempt_no"
    :subtitle="$attempt->assessment->title"
/>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<x-card variant="panel" class="mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <x-badge :value="$attempt->status" />
        @if($attempt->terminated_for_cheating)
            <x-status-badge status="danger" :label="__('assessment.terminated_for_cheating')" />
        @endif
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">{{ __('assessment.proctor_warnings') }}</dt>
        <dd class="col-sm-8">
            <span class="proctor-warning-count">{{ $attempt->proctor_warnings }}</span>
        </dd>
        <dt class="col-sm-4">{{ __('assessment.proctor_focus_loss_count') }}</dt>
        <dd class="col-sm-8">{{ $attempt->focus_loss_count }}</dd>
    </dl>
</x-card>

@if($attempt->terminated_for_cheating)
    <form method="POST" action="{{ route('admin.attempts.clear-termination', $attempt) }}" class="mb-4">
        @csrf
        <button class="btn btn-warning">{{ __('assessment.termination_cleared') }}</button>
    </form>
@endif

<x-card variant="quiet" class="mb-3">
    <h2 class="h6 mb-3">{{ __('assessment.proctor_events') }}</h2>

    @php
        $timelineItems = $attempt->proctorEvents->map(function ($event) {
            $typeKey = 'assessment.proctor_event_type_'.$event->event_type;
            $typeLabel = __($typeKey) !== $typeKey
                ? __($typeKey)
                : __('assessment.proctor_event_type_unknown');

            return [
                'time' => $event->created_at->isoFormat('LL LT'),
                'title' => $typeLabel,
                'body' => __('assessment.proctor_event_number').' '.$event->warning_number,
            ];
        })->all();
    @endphp

    @if(count($timelineItems) > 0)
        <x-timeline :items="$timelineItems" />
    @else
        <x-empty-state :title="__('assessment.attempts_empty')" icon="bi-shield-check" />
    @endif
</x-card>

@endsection
