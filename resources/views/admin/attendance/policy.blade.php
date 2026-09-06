@extends('layouts.app')
@section('title', __('attendance.policy'))
@section('content')
<x-page-header :title="__('attendance.policy')" :subtitle="__('attendance.policy_help')" />

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<form method="GET" class="row g-2 mb-4">
    <div class="col-md-6">
        <select name="offering_id" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('attendance.global_policy') }}</option>
            @foreach($offerings as $item)
                <option value="{{ $item->id }}" @selected($offering?->id === $item->id)>{{ $item->course->code }} — {{ $item->course->title }}</option>
            @endforeach
        </select>
    </div>
</form>

<form method="POST" action="{{ route('admin.attendance.policy.save') }}" class="app-card p-4">
    @csrf
    @if($offering)
        <input type="hidden" name="offering_id" value="{{ $offering->id }}">
    @endif
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">{{ __('attendance.min_percentage') }}</label>
            <input type="number" min="0" max="100" name="min_percentage" class="form-control" value="{{ old('min_percentage', $policy->min_percentage ?? 75) }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('attendance.late_grade_percentage') }}</label>
            <input type="number" min="0" max="100" name="late_grade_percentage" class="form-control" value="{{ old('late_grade_percentage', $policy->late_grade_percentage ?? 50) }}" required>
        </div>
        <div class="col-12">
            <label class="form-check">
                <input type="hidden" name="counts_toward_grade" value="0">
                <input type="checkbox" name="counts_toward_grade" value="1" class="form-check-input" @checked(old('counts_toward_grade', $policy->counts_toward_grade ?? true))>
                <span class="form-check-label">{{ __('attendance.counts_toward_grade') }}</span>
            </label>
        </div>
        <div class="col-12">
            <label class="form-check">
                <input type="hidden" name="is_enabled" value="0">
                <input type="checkbox" name="is_enabled" value="1" class="form-check-input" @checked(old('is_enabled', $policy->is_enabled ?? true))>
                <span class="form-check-label">{{ __('attendance.is_enabled') }}</span>
            </label>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">{{ __('ui.save') }}</button>
        </div>
    </div>
</form>
@endsection
