@extends('layouts.app')

@section('title', __('live.live_sessions'))

@section('content')
<x-page-header
    :title="__('live.live_sessions')"
    :subtitle="$offering->course->code"
/>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('admin.live.store', $offering) }}" class="row g-2 mb-4">
    @csrf
    <div class="col-md-4"><input name="title" class="form-control" required placeholder="{{ __('live.session_title') }}"></div>
    <div class="col-md-4"><input type="datetime-local" name="scheduled_start" class="form-control" required></div>
    <div class="col-md-2"><input type="number" name="duration_minutes" class="form-control" value="60" required aria-label="{{ __('live.duration_minutes') }}"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('live.schedule') }}</button></div>
</form>

@php
    $agenda = $sessions->groupBy(fn ($session) => optional($session->scheduled_start)->toDateString() ?? 'unscheduled');
@endphp

<section class="mb-4" aria-labelledby="live-agenda-heading">
    <h2 id="live-agenda-heading" class="h5 mb-3">{{ __('live.agenda') }}</h2>
    @if($agenda->isEmpty())
        <x-empty-state :title="__('live.agenda_empty')" />
    @else
        @foreach($agenda as $date => $daySessions)
            <div class="mb-3">
                <h3 class="h6 text-muted-theme mb-2">
                    {{ $date === 'unscheduled' ? __('live.unscheduled') : \Illuminate\Support\Carbon::parse($date)->toFormattedDateString() }}
                </h3>
                <ul class="list-unstyled spims-live-agenda mb-0">
                    @foreach($daySessions as $session)
                        <li class="d-flex flex-wrap gap-2 align-items-baseline py-2 border-bottom border-opacity-25">
                            <span class="fw-semibold">{{ optional($session->scheduled_start)->format('H:i') }}</span>
                            <span>{{ $session->title }}</span>
                            <span class="text-muted-theme small">({{ $session->duration_minutes }}{{ __('live.minutes_abbr') }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
</section>

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
        <form method="POST" action="{{ route('admin.live.attendance.override', $session) }}" class="row g-1">
            @csrf
            <div class="col-md-4"><input name="student_id" class="form-control form-control-sm" placeholder="student ULID" required></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option>PRESENT</option><option>ABSENT</option></select></div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary">{{ __('live.override') }}</button></div>
        </form>
    </div>
</div>
@endforeach
@endsection
