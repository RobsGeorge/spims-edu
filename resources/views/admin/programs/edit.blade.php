@extends('layouts.app')
@section('title', __('academics.edit_program'))
@section('content')
<x-page-header :title="__('academics.edit_program')" :subtitle="$program->code.' — '.$program->name">
    <x-slot:actions>
        <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-outline-secondary">{{ __('ui.cancel') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('admin.programs.update', $program) }}" class="card border-0 shadow-sm app-card mb-4">
    @csrf
    @method('PUT')
    <div class="card-body row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label">{{ __('academics.code') }}</label>
            <input class="form-control" value="{{ $program->code }}" disabled>
        </div>
        <div class="col-12 col-md-8">
            <label class="form-label">{{ __('academics.name') }}</label>
            <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $program->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">{{ __('academics.type') }}</label>
            <select name="type" class="form-select" required>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $program->type->value) === $type->value)>{{ $type->value }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4"><label class="form-label">{{ __('academics.max_credits') }}</label><input type="number" name="max_credits_per_semester" class="form-control" value="{{ old('max_credits_per_semester', $program->max_credits_per_semester) }}" required></div>
        <div class="col-12 col-md-4"><label class="form-label">{{ __('academics.max_courses') }}</label><input type="number" name="max_courses_per_semester" class="form-control" value="{{ old('max_courses_per_semester', $program->max_courses_per_semester) }}" required></div>
        <div class="col-12 col-md-4"><label class="form-label">{{ __('academics.max_semesters') }}</label><input type="number" name="max_semesters_to_graduate" class="form-control" value="{{ old('max_semesters_to_graduate', $program->max_semesters_to_graduate) }}" required></div>
        <div class="col-12 col-md-4"><label class="form-label">{{ __('academics.elective_credits') }}</label><input type="number" name="elective_credits_required" class="form-control" value="{{ old('elective_credits_required', $program->elective_credits_required) }}"></div>
        <div class="col-12 col-md-4"><label class="form-label">{{ __('academics.passing_threshold') }}</label><input type="number" step="0.01" name="passing_threshold" class="form-control" value="{{ old('passing_threshold', $program->passing_threshold) }}"></div>
        <div class="col-12 col-md-4">
            <label class="form-label">{{ __('academics.grading_scheme') }}</label>
            <select name="grading_scheme_id" class="form-select">
                <option value="">—</option>
                @foreach($schemes as $scheme)
                    <option value="{{ $scheme->id }}" @selected(old('grading_scheme_id', $program->grading_scheme_id) === $scheme->id)>{{ $scheme->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6"><label class="form-label">{{ __('academics.signatory_name') }}</label><input name="signatory_name" class="form-control" value="{{ old('signatory_name', $program->signatory_name) }}"></div>
        <div class="col-12 col-md-6"><label class="form-label">{{ __('academics.signatory_title') }}</label><input name="signatory_title" class="form-control" value="{{ old('signatory_title', $program->signatory_title) }}"></div>
        <div class="col-12 col-md-4 form-check mt-4">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" class="form-check-input" id="program_active" @checked(old('active', $program->active))>
            <label for="program_active" class="form-check-label">{{ __('academics.active') }}</label>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">{{ __('ui.save_changes') }}</button>
            <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-outline-secondary">{{ __('ui.cancel') }}</a>
        </div>
    </div>
</form>

@include('admin.programs._standing')
@endsection
