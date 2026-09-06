@extends('layouts.app')
@section('title', ($snapshot['quiz']['title'] ?? __('live_quiz.play')).' — '.__('live_quiz.play'))
@if(!empty($autoRefresh))
    @push('head')
        <meta http-equiv="refresh" content="2">
    @endpush
@endif
@php
    $state = $snapshot['state'] ?? '';
    $question = $snapshot['current_question'] ?? null;
    $you = $snapshot['you'] ?? null;
    $answered = (bool) ($you['answered'] ?? false);
    $showForm = $state === 'QUESTION_OPEN' && $question && ! $answered;
@endphp
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <x-page-header
            :title="$snapshot['quiz']['title'] ?? __('live_quiz.play')"
            :subtitle="__('live_quiz.play')"
            :eyebrow="__('live_quiz.student')"
        >
            <x-slot:actions>
                <a href="{{ route('live-quiz.join') }}" class="btn btn-outline-secondary btn-sm">{{ __('live_quiz.join_another') }}</a>
            </x-slot:actions>
        </x-page-header>

        <div class="border rounded-3 p-4 text-center mb-3">
            <x-status-badge :status="$state" :label="__('live_quiz.state_'.$state)" />
            @if($you)
                <div class="small text-muted-theme mt-2">{{ $you['display_name'] }}</div>
            @endif
        </div>

        @if($state === 'LOBBY')
            <div class="app-card p-4 text-center">
                <p class="mb-0">{{ __('live_quiz.waiting_lobby') }}</p>
            </div>
        @elseif($state === 'QUESTION_CLOSED')
            <div class="app-card p-4 text-center">
                @if($question)
                    <p class="fw-semibold mb-2">{{ $question['prompt'] }}</p>
                @endif
                <p class="mb-0 text-muted-theme">{{ __('live_quiz.waiting_closed') }}</p>
            </div>
        @elseif($state === 'ENDED')
            <div class="app-card p-4 text-center">
                <p class="mb-0">{{ __('live_quiz.ended') }}</p>
                @if($you && $you['score'] !== null)
                    <p class="mt-2 mb-0">{{ __('live_quiz.your_score', ['score' => $you['score']]) }}</p>
                @endif
            </div>
        @elseif($question)
            <div class="app-card p-4">
                <p class="fw-semibold mb-3">{{ $question['prompt'] }}</p>

                @if($showForm)
                    <form method="POST" action="{{ route('live-quiz.sessions.answer', [$snapshot['id'], $question['id']]) }}">
                        @csrf
                        <fieldset class="mb-3">
                            <legend class="visually-hidden">{{ __('live_quiz.choose_option') }}</legend>
                            @foreach($question['options'] as $option)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="option_id"
                                           id="option-{{ $option['id'] }}" value="{{ $option['id'] }}" required>
                                    <label class="form-check-label" for="option-{{ $option['id'] }}">{{ $option['label'] }}</label>
                                </div>
                            @endforeach
                        </fieldset>
                        <button class="btn btn-primary">{{ __('live_quiz.submit_answer') }}</button>
                    </form>
                @else
                    @if($answered && $state === 'QUESTION_OPEN')
                        <p class="text-muted-theme mb-3">{{ __('live_quiz.waiting_answered') }}</p>
                    @elseif($state === 'RESULTS')
                        <p class="text-muted-theme mb-3">{{ __('live_quiz.results') }}</p>
                    @endif
                    <ul class="list-unstyled mb-0">
                        @foreach($question['options'] as $option)
                            <li class="mb-1">
                                {{ $option['label'] }}
                                @if(array_key_exists('is_correct', $option))
                                    @if($option['is_correct'])
                                        <span class="small text-success">{{ __('live_quiz.correct') }}</span>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($you && $you['score'] !== null && in_array($state, ['RESULTS', 'ENDED'], true))
                        <p class="mt-3 mb-0">{{ __('live_quiz.your_score', ['score' => $you['score']]) }}</p>
                    @endif
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
