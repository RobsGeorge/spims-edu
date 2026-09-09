@extends('layouts.app')
@section('title', $survey->title)
@section('content')
<x-page-header
    :title="$survey->title"
    :subtitle="$survey->offering?->course ? ($survey->offering->course->code.' — '.$survey->offering->course->title) : __('feedback.school_wide')"
    :eyebrow="__('feedback.eyebrow')"
>
    <x-slot:actions>
        <a href="{{ route('student.surveys.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('feedback.back_list') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="d-flex flex-wrap gap-2 mb-3">
    <x-status-badge :status="$survey->status->value" :label="$survey->statusLabel()" />
    @if($survey->anonymous_default)
        <span class="badge-brand">{{ __('feedback.anonymous_badge') }}</span>
    @endif
    @if($submitted)
        <span class="badge-brand">{{ __('feedback.submitted') }}</span>
    @endif
</div>

@if($survey->opens_at || $survey->closes_at)
    <p class="small spims-text-dim">
        @if($survey->opens_at)
            {{ __('feedback.window_opens', ['when' => $survey->opens_at->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}
        @endif
        @if($survey->closes_at)
            {{ __('feedback.window_closes', ['when' => $survey->closes_at->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}
        @endif
    </p>
@endif

@if($submitted)
    {{-- One-response rule: show a clear thank-you state rather than re-presenting the form --}}
    <x-card variant="panel" class="mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-check-circle-fill fs-3 text-success" aria-hidden="true"></i>
            <div>
                <p class="mb-0 fw-semibold">{{ __('feedback.already_submitted_notice') }}</p>
                <p class="small spims-text-dim mb-0">{{ __('feedback.anonymous_notice') }}</p>
            </div>
        </div>
    </x-card>

    @forelse($survey->questions as $question)
        @include('surveys.partials.question', [
            'question' => $question,
            'value'    => $ownAnswers[$question->id] ?? null,
            'disabled' => true,
        ])
    @empty
        <x-empty-state :title="__('feedback.no_questions')" icon="bi-list-ul" />
    @endforelse

@elseif(! $accepting)
    {{-- Closed-window handling: survey is not accepting submissions --}}
    <x-card variant="quiet" class="mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-lock fs-3 text-warning" aria-hidden="true"></i>
            <p class="mb-0">
                @if($survey->isClosed())
                    {{ __('feedback.closed') }}
                @elseif(! $survey->isPublished())
                    {{ __('feedback.unpublished') }}
                @else
                    {{ __('feedback.outside_window') }}
                @endif
            </p>
        </div>
    </x-card>

@else
    {{-- Open survey: show the submission form --}}
    <p class="spims-text-dim">{{ __('feedback.anonymous_notice') }}</p>

    <form method="POST" action="{{ route('student.surveys.submit', $survey) }}" class="mt-3">
        @csrf
        @foreach($survey->questions as $question)
            @include('surveys.partials.question', [
                'question' => $question,
                'value'    => old('answers.'.$question->id, $ownAnswers[$question->id] ?? null),
                'disabled' => false,
            ])
        @endforeach
        <button type="submit" class="btn btn-primary">{{ __('feedback.submit') }}</button>
    </form>
@endif
@endsection
