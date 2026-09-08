@extends('layouts.app')
@section('title', __('offerings.edit_year'))
@section('content')
<x-page-header :title="__('offerings.edit_year')" />
<form method="POST" action="{{ route('admin.academic-years.update', $year) }}" class="card border-0 shadow-sm">
    @csrf
    @method('PUT')
    <div class="card-body row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label">{{ __('academics.name') }}</label>
            <input name="name" class="form-control" value="{{ old('name', $year->name) }}" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">{{ __('offerings.start_date') }}</label>
            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $year->start_date?->toDateString()) }}" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">{{ __('offerings.end_date') }}</label>
            <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $year->end_date?->toDateString()) }}" required>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">{{ __('ui.save_changes') }}</button>
            <a href="{{ route('admin.semesters.index') }}" class="btn btn-outline-secondary">{{ __('ui.cancel') }}</a>
        </div>
    </div>
</form>
@endsection
