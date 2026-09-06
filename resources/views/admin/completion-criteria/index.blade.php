@extends('layouts.app')
@section('title', __('completion.criteria_title'))
@section('content')
<h1 class="spims-title mb-3">{{ __('completion.criteria_title') }} — {{ $course->code }}</h1>
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

<form method="POST" action="{{ route('admin.completion-criteria.store', $course) }}" class="card border-0 shadow-sm mb-4">
    @csrf
    <div class="card-body row g-2">
        <div class="col-md-3">
            <select name="kind" class="form-select" required aria-label="{{ __('completion.kind') }}">
                @foreach($kinds as $kind)
                    <option value="{{ $kind->value }}">{{ $kind->value }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><input name="threshold" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="{{ __('completion.threshold') }}"></div>
        <div class="col-md-3">
            <select name="offering_id" class="form-select" aria-label="{{ __('completion.scope') }}">
                <option value="">{{ __('completion.scope_course') }}</option>
                @foreach($offerings as $offering)
                    <option value="{{ $offering->id }}">{{ $offering->id }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-check mt-2">
                <input type="hidden" name="is_required" value="0">
                <input class="form-check-input" type="checkbox" name="is_required" value="1" checked>
                {{ __('completion.is_required') }}
            </label>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('completion.add_criterion') }}</button></div>
    </div>
</form>

<div class="table-responsive spims-table-wrap">
<table class="table table-sm">
    <thead><tr><th>{{ __('completion.kind') }}</th><th>{{ __('completion.threshold') }}</th><th>{{ __('completion.scope') }}</th><th>{{ __('completion.is_required') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($criteria as $criterion)
        <tr>
            <td>{{ $criterion->kind->value }}</td>
            <td>{{ $criterion->threshold }}</td>
            <td>{{ $criterion->offering_id ? __('completion.scope_offering') : __('completion.scope_course') }}</td>
            <td>{{ $criterion->is_required ? __('completion.required_yes') : __('completion.required_no') }}</td>
            <td>
                <form method="POST" action="{{ route('admin.completion-criteria.destroy', $criterion) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">{{ __('completion.remove') }}</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-muted-theme">{{ __('completion.no_criteria') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
