@extends('layouts.app')
@section('title', __('assessment.assessments'))
@section('content')
<h1 class="spims-title mb-3">{{ __('assessment.assessments') }} — {{ $offering->course->code }}</h1>
<x-card variant="panel" tag="form" method="POST" action="{{ route('admin.assessments.store', $offering) }}">
    @csrf
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label" for="assessment_title">{{ __('assessment.title') }}</label>
            <input id="assessment_title" name="title" class="form-control" placeholder="{{ __('assessment.title') }}" required>
        </div>
        <div class="col-md-3">
            <select name="mode" class="form-select" aria-label="{{ __('assessment.mode_exam') }}">
                <option value="EXAM">{{ __('assessment.mode_exam') }}</option>
                <option value="QUIZ">{{ __('assessment.mode_quiz') }}</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="time_limit_minutes">{{ __('assessment.minutes_ph') }}</label>
            <input id="time_limit_minutes" type="number" name="time_limit_minutes" class="form-control" placeholder="{{ __('assessment.minutes_ph') }}" value="30">
        </div>
        <div class="col-md-3"><input type="number" name="attempts_allowed" class="form-control" value="1" aria-label="{{ __('assessment.attempts_allowed') }}"></div>
        <div class="col-md-3"><input type="number" name="max_points" class="form-control" value="100" aria-label="{{ __('assessment.max_points') }}"></div>
        <div class="col-md-3">
            <select name="draw_from_bank_id" class="form-select" aria-label="{{ __('assessment.banks') }}">
                <option value="">{{ __('assessment.fixed_questions') }}</option>
                @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="questions_to_draw" class="form-control" placeholder="{{ __('assessment.draw_n') }}" aria-label="{{ __('assessment.draw_n') }}">
            <p class="form-text mb-0">{{ __('assessment.draw_hint') }}</p>
        </div>
        <div class="col-md-3">
            <select name="component_id" class="form-select" aria-label="{{ __('assessment.component') }}">
                <option value="">{{ __('assessment.no_component') }}</option>
                @foreach($components as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-12"><button class="btn btn-primary">{{ __('ui.save') }}</button></div>
    </div>
</x-card>
@endsection
