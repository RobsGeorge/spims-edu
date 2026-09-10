@extends('layouts.app')
@section('title', __('live_quiz.join_title'))
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <x-page-header :title="__('live_quiz.join_title')" :subtitle="__('live_quiz.join_help')" />
        <x-card variant="panel" class="p-4">
            <form method="POST" action="{{ route('live-quiz.join.store') }}">
                @csrf
                <x-field name="code" :label="__('live_quiz.join_code')" :required="true"
                         :error="$errors->first('code')">
                    <input id="code" name="code" class="form-control" required
                           value="{{ old('code') }}" autocomplete="one-time-code" dir="ltr"
                           placeholder="{{ __('live_quiz.join_code_placeholder') }}">
                </x-field>
                <button type="submit" class="btn btn-primary w-100">
                    {{ __('live_quiz.join_submit') }}
                </button>
            </form>
        </x-card>
    </div>
</div>
@endsection
