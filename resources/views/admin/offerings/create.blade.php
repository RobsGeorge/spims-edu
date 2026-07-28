@extends('layouts.app')
@section('title', __('offerings.create_offering'))
@section('content')
<h1 class="spims-title mb-3">{{ __('offerings.create_offering') }}</h1>
<form method="POST" action="{{ route('admin.offerings.store') }}" class="card border-0 shadow-sm">
    @csrf
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">{{ __('ui.nav_courses') }}</label>
            <select name="course_id" class="form-select" required>
                @foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->code }} — {{ $course->title }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">{{ __('offerings.mode') }}</label>
            <select name="mode" class="form-select" required>
                @foreach($modes as $mode)<option value="{{ $mode->value }}">{{ $mode->value }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">{{ __('offerings.semester') }}</label>
            <select name="semester_id" class="form-select">
                <option value="">—</option>
                @foreach($semesters as $semester)<option value="{{ $semester->id }}">{{ $semester->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">{{ __('offerings.seat_capacity') }}</label><input type="number" name="seat_capacity" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">{{ __('offerings.attendance_threshold') }}</label><input type="number" step="0.01" name="attendance_threshold_percent" class="form-control" value="60"></div>
        <div class="col-md-3 form-check mt-4"><input type="checkbox" name="clone" value="1" class="form-check-input" id="clone" checked><label for="clone" class="form-check-label">{{ __('offerings.clone_week1') }}</label></div>
        <div class="col-12"><button class="btn btn-primary">{{ __('ui.save') }}</button></div>
    </div>
</form>
@endsection
