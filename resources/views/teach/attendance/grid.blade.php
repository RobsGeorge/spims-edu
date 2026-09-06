@extends('layouts.app')
@section('title', $session->title.' — '.__('attendance.grid'))
@section('content')
<x-page-header
    :title="$session->title"
    :subtitle="__('attendance.grid')"
>
    <x-slot:actions>
        <a href="{{ route('teach.attendance.index', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('attendance.sessions') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-6">
        <input name="q" value="{{ $search }}" class="form-control" placeholder="{{ __('attendance.search_students') }}">
    </div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">{{ __('attendance.search_students') }}</button></div>
</form>

<form method="POST" action="{{ route('teach.attendance.mark', [$offering, $session]) }}">
    @csrf
    <input type="hidden" name="lock_version" value="{{ $session->lock_version }}">
    <div class="table-responsive mb-3">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ __('teach.students') }}</th>
                    <th>{{ __('attendance.status') }}</th>
                    <th>{{ __('attendance.minutes_attended') }}</th>
                    <th>{{ __('attendance.excuse_reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($enrollments as $i => $enrollment)
                    @php $mark = $marks->get($enrollment->student_id); @endphp
                    <tr>
                        <td>
                            <input type="hidden" name="marks[{{ $i }}][student_id]" value="{{ $enrollment->student_id }}">
                            <strong>{{ $enrollment->student->first_name }} {{ $enrollment->student->last_name }}</strong>
                            <div class="small text-muted-theme">{{ $enrollment->student->email }}</div>
                        </td>
                        <td>
                            <select name="marks[{{ $i }}][status]" class="form-select form-select-sm">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" @selected(($mark?->status->value ?? 'ABSENT') === $status->value)>{{ __('attendance.status_'.$status->value) }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" min="0" name="marks[{{ $i }}][minutes_attended]" class="form-control form-control-sm" value="{{ $mark?->minutes_attended ?? 0 }}"></td>
                        <td><input name="marks[{{ $i }}][excuse_reason]" class="form-control form-control-sm" value="{{ $mark?->excuse_reason }}"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" @disabled($session->isClosed())>{{ __('attendance.mark_roster') }}</button>
    </div>
</form>

<div class="d-flex flex-wrap gap-2 mt-3">
    <form method="POST" action="{{ route('teach.attendance.fill-missing', [$offering, $session]) }}">
        @csrf
        <button class="btn btn-outline-secondary" @disabled($session->isClosed())>{{ __('attendance.fill_missing') }}</button>
    </form>
    @if($session->isClosed())
        <form method="POST" action="{{ route('teach.attendance.reopen', [$offering, $session]) }}">
            @csrf
            <button class="btn btn-outline-warning">{{ __('attendance.reopen_session') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('teach.attendance.close', [$offering, $session]) }}">
            @csrf
            <button class="btn btn-outline-danger">{{ __('attendance.close_session') }}</button>
        </form>
        <form method="POST" action="{{ route('teach.attendance.code', [$offering, $session]) }}" class="d-flex gap-2">
            @csrf
            <input type="number" name="ttl_minutes" value="30" class="form-control form-control-sm" style="width:6rem" aria-label="{{ __('attendance.duration_minutes') }}">
            <button class="btn btn-outline-primary">{{ __('attendance.issue_code') }}</button>
        </form>
    @endif
</div>
@endsection
