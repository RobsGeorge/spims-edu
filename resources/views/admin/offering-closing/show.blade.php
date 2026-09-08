@extends('layouts.app')
@section('title', __('completion.closing_title'))
@section('content')
<x-page-header :title="__('completion.closing_title').' — '.$offering->course->code" :subtitle="__('completion.status').': '.$status->value" />
@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'completion', 'prefix' => 'admin'])
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap gap-2 mb-4">
    <form method="POST" action="{{ route('admin.offering-closing.evaluate', $offering) }}">@csrf
        <button class="btn btn-outline-primary">{{ __('completion.evaluate') }}</button>
    </form>
    @if($status->value === 'OPEN')
    <form method="POST" action="{{ route('admin.offering-closing.lock', $offering) }}">@csrf
        <button class="btn btn-primary">{{ __('completion.lock_grading') }}</button>
    </form>
    @endif
    @if($status->value === 'GRADING_LOCKED')
    <form method="POST" action="{{ route('admin.offering-closing.announce', $offering) }}">@csrf
        <button class="btn btn-primary">{{ __('completion.announce') }}</button>
    </form>
    @endif
    @if($status->value === 'ANNOUNCED')
    <form method="POST" action="{{ route('admin.offering-closing.close', $offering) }}">@csrf
        <button class="btn btn-primary">{{ __('completion.close') }}</button>
    </form>
    @endif
</div>

@if($status->value === 'GRADING_LOCKED')
<form method="POST" action="{{ route('admin.offering-closing.grace', $offering) }}" class="card border-0 shadow-sm mb-4">
    @csrf
    <div class="card-body">
        <h2 class="h6">{{ __('completion.grace_marks') }}</h2>
        @forelse($enrollments as $enrollment)
            <div class="row g-2 mb-2">
                <input type="hidden" name="student_id[]" value="{{ $enrollment->student_id }}">
                <div class="col-12 col-md-6">{{ $enrollment->student?->email }}</div>
                <div class="col-12 col-md-3"><input name="amount[]" type="number" step="0.01" class="form-control" value="{{ $graceMarks[$enrollment->student_id] ?? 0 }}"></div>
            </div>
        @empty
            <p class="text-muted-theme mb-0">{{ __('completion.no_results') }}</p>
        @endforelse
        <button class="btn btn-outline-primary">{{ __('completion.apply_grace') }}</button>
    </div>
</form>
@endif

<div class="table-responsive spims-table-wrap">
<table class="table table-sm">
    <thead><tr><th>{{ __('completion.student') }}</th><th>{{ __('completion.outcome') }}</th><th>{{ __('completion.met_criteria') }}</th></tr></thead>
    <tbody>
    @forelse($results as $result)
        <tr>
            <td>{{ $result->student?->email }}</td>
            <td>{{ $result->outcome->value }}</td>
            <td class="small">
                @foreach($result->met_criteria ?? [] as $row)
                    {{ $row['kind'] }}: {{ $row['passed'] ? __('completion.passed') : __('completion.failed') }}@if(! $loop->last), @endif
                @endforeach
            </td>
        </tr>
    @empty
        <tr><td colspan="3" class="text-muted-theme">{{ __('completion.no_results') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
