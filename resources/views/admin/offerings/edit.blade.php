@extends('layouts.app')
@section('title', __('offerings.edit_offering'))
@section('content')
<h1 class="spims-title mb-3">{{ __('offerings.edit_offering') }}</h1>
<p class="spims-text-dim">{{ $offering->course->code }} — {{ $offering->course->title }}</p>
<x-card variant="panel" tag="form" method="POST" action="{{ route('admin.offerings.update', $offering) }}">
    @csrf
    @method('PUT')
    <div class="row g-3">
        @if($offering->mode->value === 'COHORT')
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.semester') }}</label>
            <select name="semester_id" class="form-select">
                <option value="">—</option>
                @foreach($semesters as $semester)
                    <option value="{{ $semester->id }}" @selected(old('semester_id', $offering->semester_id) === $semester->id)>{{ $semester->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.seat_capacity') }}</label>
            <input type="number" name="seat_capacity" class="form-control" value="{{ old('seat_capacity', $offering->seat_capacity) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.attendance_threshold') }}</label>
            <input type="number" step="0.01" name="attendance_threshold_percent" class="form-control" value="{{ old('attendance_threshold_percent', $offering->attendance_threshold_percent) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.start_date') }}</label>
            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $offering->start_date?->toDateString()) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.end_date') }}</label>
            <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $offering->end_date?->toDateString()) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.status') }}</label>
            <select name="status" class="form-select" required>
                @foreach($statuses as $status)
                    @php $statusVal = $status->value; @endphp
                    <option value="{{ $statusVal }}" @selected(old('status', $offering->status->value) === $statusVal)>{{ $statusVal }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">{{ __('ui.save_changes') }}</button>
            <a href="{{ route('admin.offerings.show', $offering) }}" class="btn btn-outline-secondary">{{ __('ui.cancel') }}</a>
        </div>
    </div>
</x-card>
@endsection
