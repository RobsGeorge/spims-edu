@extends('layouts.app')
@section('title', __('staff.live_quiz.title').' — '.$offering->course->code)
@section('content')
<x-page-header
    :title="__('staff.live_quiz.title')"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
    :eyebrow="__('teach.workspace')"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'live_quiz']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'live_quiz', 'prefix' => 'teach'])

<form method="POST" action="{{ route('teach.live-quiz.store', $offering) }}" class="row g-2 mb-4 mt-3">
    @csrf
    <div class="col-md-4"><input name="title" class="form-control" required placeholder="{{ __('staff.live_quiz.title_placeholder') }}"></div>
    <div class="col-md-8"><input name="prompt" class="form-control" placeholder="{{ __('staff.live_quiz.first_prompt') }}"></div>
    <div class="col-md-3"><input name="options[]" class="form-control" placeholder="{{ __('staff.live_quiz.option_a') }}"></div>
    <div class="col-md-3"><input name="options[]" class="form-control" placeholder="{{ __('staff.live_quiz.option_b') }}"></div>
    <div class="col-md-2">
        <select name="correct_index" class="form-select" aria-label="{{ __('staff.live_quiz.correct') }}">
            <option value="0">A</option>
            <option value="1">B</option>
        </select>
    </div>
    <div class="col-md-2"><input type="number" name="time_limit_seconds" class="form-control" value="30" min="1" aria-label="{{ __('staff.live_quiz.seconds') }}"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('staff.live_quiz.create') }}</button></div>
</form>

@forelse($quizzes as $quiz)
    @php
        $active = $quiz->sessions->first(fn ($session) => $session->state->value !== 'ENDED');
    @endphp
    <div class="border rounded-3 p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <strong>{{ $quiz->title }}</strong>
                <x-status-badge :status="$quiz->status->value" :label="__('staff.live_quiz.status_'.$quiz->status->value)" />
            </div>
            <div class="d-flex gap-2">
                @if($active)
                    <a class="btn btn-sm btn-primary" href="{{ route('teach.live-quiz.session', [$offering, $active]) }}">{{ __('staff.live_quiz.open_console') }}</a>
                @else
                    <form method="POST" action="{{ route('teach.live-quiz.start', [$offering, $quiz]) }}">
                        @csrf
                        <button class="btn btn-sm btn-primary">{{ __('staff.live_quiz.start') }}</button>
                    </form>
                @endif
            </div>
        </div>
        @foreach($quiz->questions as $question)
            <div class="small mb-1">{{ $question->position }}. {{ $question->prompt }}</div>
        @endforeach
        <form method="POST" action="{{ route('teach.live-quiz.questions.store', [$offering, $quiz]) }}" class="row g-2 mt-2">
            @csrf
            <div class="col-md-4"><input name="prompt" class="form-control form-control-sm" required placeholder="{{ __('staff.live_quiz.prompt') }}"></div>
            <div class="col-md-3"><input name="options[]" class="form-control form-control-sm" required placeholder="{{ __('staff.live_quiz.option_a') }}"></div>
            <div class="col-md-3"><input name="options[]" class="form-control form-control-sm" required placeholder="{{ __('staff.live_quiz.option_b') }}"></div>
            <div class="col-md-1">
                <select name="correct_index" class="form-select form-select-sm">
                    <option value="0">A</option>
                    <option value="1">B</option>
                </select>
            </div>
            <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">{{ __('ui.save') }}</button></div>
        </form>
    </div>
@empty
    <x-empty-state :title="__('staff.live_quiz.empty')" icon="bi-lightning" />
@endforelse
@endsection
