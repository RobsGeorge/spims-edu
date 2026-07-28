@extends('layouts.app')
@section('title', __('live.live_sessions'))
@section('content')
<h1 class="spims-title mb-3">{{ __('live.live_sessions') }} — {{ $offering->course->code }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('admin.live.store', $offering) }}" class="row g-2 mb-4">@csrf
    <div class="col-md-4"><input name="title" class="form-control" required placeholder="Title"></div>
    <div class="col-md-4"><input type="datetime-local" name="scheduled_start" class="form-control" required></div>
    <div class="col-md-2"><input type="number" name="duration_minutes" class="form-control" value="60" required></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Schedule</button></div>
</form>
@foreach($sessions as $session)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6">{{ $session->title }} — {{ $session->scheduled_start }} ({{ $session->duration_minutes }}m)</h2>
        <p class="small mb-2">Zoom: {{ $session->zoom_meeting_id }} · {{ $session->recording_url }}</p>
        <ul class="small">
            @foreach($session->attendance as $row)
                <li>{{ $row->student->email }} — {{ $row->status->value }} ({{ $row->minutes_attended }}m)</li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.live.attendance.override', $session) }}" class="row g-1">@csrf
            <div class="col-md-4"><input name="student_id" class="form-control form-control-sm" placeholder="student ULID" required></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option>PRESENT</option><option>ABSENT</option></select></div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary">Override</button></div>
        </form>
    </div>
</div>
@endforeach
@endsection
