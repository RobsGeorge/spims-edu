@extends('layouts.app')
@section('title', __('academics.edit_course'))
@section('content')
<x-page-header :title="__('academics.edit_course')" :subtitle="$course->code" />
<form method="POST" action="{{ route('admin.courses.update', $course) }}">
    @csrf
    @method('PUT')
    <x-card variant="panel"><div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">{{ __('academics.code') }}</label>
            <input class="form-control" value="{{ $course->code }}" disabled>
        </div>
        <div class="col-md-9">
            <label class="form-label">{{ __('academics.title') }}</label>
            <input name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $course->title) }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3"><label class="form-label">{{ __('academics.credits') }}</label><input type="number" name="credit_hours" class="form-control" value="{{ old('credit_hours', $course->credit_hours) }}" required></div>
        <div class="col-md-3"><label class="form-label">{{ __('academics.price_usd') }}</label><input type="number" name="default_price_usd" class="form-control" value="{{ old('default_price_usd', $course->default_price_usd) }}"></div>
        <div class="col-md-3"><label class="form-label">{{ __('academics.price_egp') }}</label><input type="number" name="default_price_egp" class="form-control" value="{{ old('default_price_egp', $course->default_price_egp) }}"></div>
        <div class="col-md-3">
            <label class="form-label">{{ __('academics.assessment_template') }}</label>
            <select name="assessment_template_id" class="form-select">
                <option value="">—</option>
                @foreach($templates as $t)
                    <option value="{{ $t->id }}" @selected(old('assessment_template_id', $course->assessment_template_id) === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">{{ __('academics.passing_threshold') }}</label><input type="number" step="0.01" name="passing_threshold" class="form-control" value="{{ old('passing_threshold', $course->passing_threshold) }}"></div>
        <div class="col-md-3 form-check mt-4">
            <input type="hidden" name="is_free" value="0">
            <input type="checkbox" name="is_free" value="1" class="form-check-input" id="is_free" @checked(old('is_free', $course->is_free))>
            <label for="is_free" class="form-check-label">{{ __('academics.is_free') }}</label>
        </div>
        <div class="col-md-3 form-check mt-4">
            <input type="hidden" name="is_standalone" value="0">
            <input type="checkbox" name="is_standalone" value="1" class="form-check-input" id="is_standalone" @checked(old('is_standalone', $course->is_standalone))>
            <label for="is_standalone" class="form-check-label">{{ __('academics.is_standalone') }}</label>
        </div>
        <div class="col-md-3 form-check mt-4">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" class="form-check-input" id="course_active" @checked(old('active', $course->active))>
            <label for="course_active" class="form-check-label">{{ __('academics.active') }}</label>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">{{ __('ui.save_changes') }}</button>
            <a href="{{ route('admin.courses.show', $course) }}" class="btn btn-outline-secondary">{{ __('ui.cancel') }}</a>
        </div>
    </div></x-card>
</form>
@endsection
