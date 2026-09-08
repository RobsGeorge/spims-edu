@extends('layouts.app')
@section('title', __('live_quiz.join_title'))
@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-6">
        <x-page-header :title="__('live_quiz.join_title')" :subtitle="__('live_quiz.join_help')" />
        <form method="POST" action="{{ route('live-quiz.join.store') }}" class="app-card p-4">
            @csrf
            <label class="form-label" for="live-quiz-code">{{ __('live_quiz.join_code') }}</label>
            <input id="live-quiz-code" name="code" class="form-control mb-3" required
                   value="{{ old('code') }}" autocomplete="one-time-code" dir="ltr"
                   placeholder="{{ __('live_quiz.join_code_placeholder') }}">
            <button class="btn btn-primary">{{ __('live_quiz.join_submit') }}</button>
        </form>
    </div>
</div>
@endsection
