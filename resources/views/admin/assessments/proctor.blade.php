@extends('layouts.app')
@section('title', __('assessment.assessments'))
@section('content')
<x-page-header :title="$attempt->student->email.' — #'.$attempt->attempt_no" />
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<p>{{ $attempt->assessment->title }} · {{ $attempt->status->value }}
    @if($attempt->terminated_for_cheating)<span class="badge bg-danger">terminated for cheating</span>@endif
</p>
<p>{{ __('assessment.component') ?? '' }} proctor_warnings: {{ $attempt->proctor_warnings }} · focus_loss_count: {{ $attempt->focus_loss_count }}</p>

@if($attempt->terminated_for_cheating)
    <form method="POST" action="{{ route('admin.attempts.clear-termination', $attempt) }}" class="mb-4">
        @csrf
        <button class="btn btn-warning">{{ __('assessment.termination_cleared') }}</button>
    </form>
@endif

<h2 class="h6">Proctor events</h2>
<table class="table table-sm">
    <thead><tr><th>#</th><th>type</th><th>at</th></tr></thead>
    <tbody>
    @foreach($attempt->proctorEvents as $event)
        <tr>
            <td>{{ $event->warning_number }}</td>
            <td>{{ $event->event_type }}</td>
            <td>{{ $event->created_at }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
