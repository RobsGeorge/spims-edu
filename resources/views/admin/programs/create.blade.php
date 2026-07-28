@extends('layouts.app')
@section('title', __('academics.create_program'))
@section('content')
<h1 class="spims-title mb-3">{{ __('academics.create_program') }}</h1>
<form method="POST" action="{{ route('admin.programs.store') }}" class="card border-0 shadow-sm">
    @csrf
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">{{ __('academics.code') }}</label><input name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" required>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-8"><label class="form-label">{{ __('academics.name') }}</label><input name="name" class="form-control" value="{{ old('name') }}" required></div>
        <div class="col-md-4"><label class="form-label">{{ __('academics.type') }}</label>
            <select name="type" class="form-select" required>
                @foreach($types as $type)<option value="{{ $type->value }}">{{ $type->value }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4"><label class="form-label">{{ __('academics.max_credits') }}</label><input type="number" name="max_credits_per_semester" class="form-control" value="{{ old('max_credits_per_semester', 18) }}" required></div>
        <div class="col-md-4"><label class="form-label">{{ __('academics.max_courses') }}</label><input type="number" name="max_courses_per_semester" class="form-control" value="{{ old('max_courses_per_semester', 6) }}" required></div>
        <div class="col-md-4"><label class="form-label">{{ __('academics.max_semesters') }}</label><input type="number" name="max_semesters_to_graduate" class="form-control" value="{{ old('max_semesters_to_graduate', 8) }}" required></div>
        <div class="col-md-4"><label class="form-label">{{ __('academics.elective_credits') }}</label><input type="number" name="elective_credits_required" class="form-control" value="{{ old('elective_credits_required', 0) }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('academics.passing_threshold') }}</label><input type="number" step="0.01" name="passing_threshold" class="form-control" value="{{ old('passing_threshold', 60) }}"></div>
        <div class="col-md-4"><label class="form-label">{{ __('academics.grading_scheme') }}</label>
            <select name="grading_scheme_id" class="form-select">
                <option value="">—</option>
                @foreach($schemes as $scheme)<option value="{{ $scheme->id }}">{{ $scheme->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">{{ __('academics.signatory_name') }}</label><input name="signatory_name" class="form-control" value="{{ old('signatory_name') }}"></div>
        <div class="col-md-6"><label class="form-label">{{ __('academics.signatory_title') }}</label><input name="signatory_title" class="form-control" value="{{ old('signatory_title') }}"></div>
        <div class="col-12"><button class="btn btn-primary">{{ __('ui.save') }}</button></div>
    </div>
</form>
@endsection
