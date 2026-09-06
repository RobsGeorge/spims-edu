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

<p class="text-muted-theme">{{ __('feedback.anonymous_notice') }}</p>

@if($submitted)
    <div class="alert alert-success">{{ __('feedback.already_submitted_notice') }}</div>
@elseif(! $accepting)
    <div class="alert alert-warning">
        @if($survey->isClosed())
            {{ __('feedback.closed') }}
        @elseif(! $survey->isPublished())
            {{ __('feedback.unpublished') }}
        @else
            {{ __('feedback.outside_window') }}
        @endif
    </div>
@endif

@if($survey->opens_at || $survey->closes_at)
    <p class="small text-muted-theme">
        @if($survey->opens_at)
            {{ __('feedback.window_opens', ['when' => $survey->opens_at->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}
        @endif
        @if($survey->closes_at)
            {{ __('feedback.window_closes', ['when' => $survey->closes_at->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}
        @endif
    </p>
@endif

@if($canSubmit)
    <form method="POST" action="{{ route('student.surveys.submit', $survey) }}" class="mt-3">
        @csrf
        @foreach($survey->questions as $question)
            @include('surveys.partials.question', [
                'question' => $question,
                'value' => old('answers.'.$question->id, $ownAnswers[$question->id] ?? null),
                'disabled' => false,
            ])
        @endforeach
        <button class="btn btn-primary">{{ __('feedback.submit') }}</button>
    </form>
@else
    <div class="mt-3">
        @forelse($survey->questions as $question)
            @include('surveys.partials.question', [
                'question' => $question,
                'value' => $ownAnswers[$question->id] ?? null,
                'disabled' => true,
            ])
        @empty
            <x-empty-state :title="__('feedback.no_questions')" icon="bi-list-ul" />
        @endforelse
    </div>
@endif
@endsection
