@extends('layouts.app')
@section('title', __('completion.closing_title'))
@section('content')
<h1 class="spims-title mb-3">{{ __('completion.closing_title') }} — {{ $offering->course->code }}</h1>
<p class="text-muted-theme">{{ __('completion.status') }}: <strong>{{ $status->value }}</strong></p>
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
        @foreach($results as $result)
            <div class="row g-2 mb-2">
                <input type="hidden" name="student_id[]" value="{{ $result->student_id }}">
                <div class="col-md-6">{{ $result->student?->email }}</div>
                <div class="col-md-3"><input name="amount[]" type="number" step="0.01" class="form-control" value="0"></div>
            </div>
        @endforeach
        <button class="btn btn-outline-primary">{{ __('completion.apply_grace') }}</button>
    </div>
</form>
@endif

<div class="table-responsive spims-table-wrap">
<table class="table table-sm">
    <thead><tr><th>{{ __('completion.student') }}</th><th>{{ __('completion.outcome') }}</th></tr></thead>
    <tbody>
    @forelse($results as $result)
        <tr>
            <td>{{ $result->student?->email }}</td>
            <td>{{ $result->outcome->value }}</td>
        </tr>
    @empty
        <tr><td colspan="2" class="text-muted-theme">{{ __('completion.no_results') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
