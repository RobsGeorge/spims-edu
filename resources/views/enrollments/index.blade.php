@extends('layouts.app')
@section('title', __('ui.nav_enrollments'))
@section('content')
<x-page-header
    :title="__('ui.nav_enrollments')"
    :subtitle="__('enrollment.subtitle')"
/>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 spims-title">{{ __('enrollment.register') }}</h2>
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6 spims-title">{{ __('enrollment.degree_audit') }}</h2>
        @forelse($programs as $sp)
            <div class="py-2 border-bottom border-opacity-25 d-flex flex-wrap justify-content-between gap-2 align-items-start">
                <div>
                    <a href="{{ route('enrollments.audit', $sp) }}">{{ __('enrollment.degree_audit') }} — {{ $sp->program->code }}</a>
                    <div class="small text-muted-theme">{{ __('advising.what_if_hint') }}</div>
                </div>
                <a class="btn btn-sm btn-outline-primary align-self-center" href="{{ route('enrollments.audit', $sp) }}">{{ __('advising.what_if') }}</a>
            </div>
        @empty
            <x-empty-state :title="__('enrollment.no_programs')" icon="bi-journal-check" />
        @endforelse
    </div>
</div>

@if($enrollments->isEmpty())
    <x-empty-state :title="__('enrollment.no_enrollments')" icon="bi-journal-bookmark" />
@else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('academics.code') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th>{{ __('learn.progress') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($enrollments as $enrollment)
                    <tr>
                        <td>{{ $enrollment->offering->course->code }}</td>
                        <td>{{ $enrollment->status->value }}</td>
                        <td>{{ number_format($enrollment->progress_percent, 0) }}%</td>
                        <td class="d-flex gap-1 flex-wrap justify-content-end">
                            @if(in_array($enrollment->status->value, ['ENROLLED', 'COMPLETED'], true))
                            <a class="btn btn-sm btn-primary" href="{{ route('learn.offering', $enrollment->offering) }}">{{ __('learning.open_player') }}</a>
                            @endif
                            @if($enrollment->status->value === 'ENROLLED')
                            <form method="POST" action="{{ route('enrollments.drop', $enrollment) }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('enrollment.drop') }}</button></form>
                            <form method="POST" action="{{ route('enrollments.withdraw', $enrollment) }}">@csrf<button class="btn btn-sm btn-outline-warning">{{ __('enrollment.withdraw') }}</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
