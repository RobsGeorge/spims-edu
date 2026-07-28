@extends('layouts.app')
@section('title', __('ui.nav_enrollments'))
@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_enrollments') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('enrollment.register') }}</h2>
        <form method="POST" action="{{ route('enrollments.store') }}" class="row g-2">
            @csrf
            <div class="col-md-5">
                <select name="offering_id" class="form-select" required>
                    @foreach($offerings as $offering)
                        <option value="{{ $offering->id }}">{{ $offering->course->code }} ({{ $offering->mode->value }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <select name="student_program_id" class="form-select">
                    <option value="">{{ __('enrollment.standalone_or_none') }}</option>
                    @foreach($programs as $sp)
                        <option value="{{ $sp->id }}">{{ $sp->program->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100">{{ __('enrollment.register') }}</button></div>
        </form>
        @error('enrollment')<div class="text-danger mt-2">{{ $message }}</div>@enderror
    </div>
</div>

@foreach($programs as $sp)
    <p><a href="{{ route('enrollments.audit', $sp) }}">{{ __('enrollment.degree_audit') }} — {{ $sp->program->code }}</a></p>
@endforeach

<table class="table">
    <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($enrollments as $enrollment)
        <tr>
            <td>{{ $enrollment->offering->course->code }}</td>
            <td>{{ $enrollment->status->value }}</td>
            <td class="d-flex gap-1">
                @if($enrollment->status->value === 'ENROLLED')
                <form method="POST" action="{{ route('enrollments.drop', $enrollment) }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('enrollment.drop') }}</button></form>
                <form method="POST" action="{{ route('enrollments.withdraw', $enrollment) }}">@csrf<button class="btn btn-sm btn-outline-warning">{{ __('enrollment.withdraw') }}</button></form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
