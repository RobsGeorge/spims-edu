@extends('layouts.app')
@section('title', __('staff.live_quiz.console').' — '.$quiz->title)
@if(!empty($autoRefresh))
    @push('head')
        <meta http-equiv="refresh" content="2">
    @endpush
@endif
@section('content')
<x-page-header
    :title="$quiz->title"
    :subtitle="__('staff.live_quiz.console')"
    :eyebrow="__('staff.live_quiz.host')"
>
    <x-slot:actions>
        <a href="{{ route('teach.live-quiz.session', [$offering, $session]) }}" class="btn btn-outline-secondary btn-sm">{{ __('staff.live_quiz.refresh') }}</a>
        <a href="{{ route('teach.live-quiz.index', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('staff.live_quiz.back_list') }}</a>
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'live_quiz', 'prefix' => 'teach'])

<div class="border rounded-3 p-4 text-center mb-3 mt-3">
    <div class="small spims-text-dim">{{ __('staff.live_quiz.join_code') }}</div>
    <div class="display-5 fw-bold spims-join-code" dir="ltr">{{ $session->join_code }}</div>
    <p class="small spims-text-dim mt-2 mb-0">{{ __('staff.live_quiz.student_join_at', ['url' => route('live-quiz.join')]) }}</p>
    <x-status-badge :status="$session->state->value" :label="__('staff.live_quiz.state_'.$session->state->value)" />
    <div class="small spims-text-dim mt-2">{{ __('staff.live_quiz.participants', ['count' => $session->participants->count()]) }}</div>
</div>

@if($session->currentQuestion)
    <div class="border rounded-3 p-3 mb-3">
        <strong>{{ $session->currentQuestion->prompt }}</strong>
        <ul class="mb-0 mt-2">
            @foreach($session->currentQuestion->options as $option)
                <li>{{ $option->label }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="d-flex flex-wrap gap-2 mb-4">
    @if($session->state->value === 'QUESTION_OPEN')
        <form method="POST" action="{{ route('teach.live-quiz.close', [$offering, $session]) }}">
            @csrf
            <button class="btn btn-warning">{{ __('staff.live_quiz.close_question') }}</button>
        </form>
    @elseif($session->state->value === 'QUESTION_CLOSED')
        <form method="POST" action="{{ route('teach.live-quiz.results', [$offering, $session]) }}">
            @csrf
            <button class="btn btn-primary">{{ __('staff.live_quiz.show_results') }}</button>
        </form>
    @endif
    @if($session->state->value !== 'ENDED')
        <form method="POST" action="{{ route('teach.live-quiz.end', [$offering, $session]) }}">
            @csrf
            <button class="btn btn-outline-danger">{{ __('staff.live_quiz.end') }}</button>
        </form>
    @endif
</div>

@if(in_array($session->state->value, ['LOBBY', 'QUESTION_CLOSED', 'RESULTS'], true))
    <h2 class="h6">{{ __('staff.live_quiz.launch') }}</h2>
    @foreach($quiz->questions as $question)
        <form method="POST" action="{{ route('teach.live-quiz.launch', [$offering, $session]) }}" class="spims-live-quiz-launch border rounded-3 p-2 mb-2">
            @csrf
            <input type="hidden" name="question_id" value="{{ $question->id }}">
            <span>{{ $question->position }}. {{ $question->prompt }}</span>
            <button class="btn btn-sm btn-outline-primary">{{ __('staff.live_quiz.launch') }}</button>
        </form>
    @endforeach
@endif
@endsection
