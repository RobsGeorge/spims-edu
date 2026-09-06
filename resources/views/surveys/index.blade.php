@extends('layouts.app')
@section('title', __('feedback.inbox_title'))
@section('content')
<x-page-header :title="__('feedback.inbox_title')" :subtitle="__('feedback.inbox_sub')" :eyebrow="__('feedback.eyebrow')" />

@forelse($surveys as $survey)
    @php
        $isSubmitted = isset($submittedIds[$survey->id]);
        $open = $survey->isAcceptingSubmissions() && ! $isSubmitted;
    @endphp
    <div class="app-card border rounded-3 p-3 mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <strong>{{ $survey->title }}</strong>
                <div class="small text-muted-theme">
                    <x-status-badge :status="$survey->status->value" :label="$survey->statusLabel()" />
                    @if($survey->offering?->course)
                        {{ $survey->offering->course->code }}
                    @else
                        {{ __('feedback.school_wide') }}
                    @endif
                    @if($survey->anonymous_default)
                        · {{ __('feedback.anonymous_badge') }}
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if($isSubmitted)
                    <span class="badge-brand">{{ __('feedback.submitted') }}</span>
                @elseif($open)
                    <span class="badge-brand">{{ __('feedback.open') }}</span>
                @endif
                <a class="btn btn-sm {{ $open ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('student.surveys.show', $survey) }}">
                    {{ $open ? __('feedback.fill') : __('feedback.view') }}
                </a>
            </div>
        </div>
    </div>
@empty
    <x-empty-state :title="__('feedback.inbox_empty')" icon="bi-clipboard-check" />
@endforelse
@endsection
