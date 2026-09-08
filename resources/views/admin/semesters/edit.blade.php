@extends('layouts.app')
@section('title', __('offerings.edit_semester'))
@section('content')
<h1 class="spims-title mb-3">{{ __('offerings.edit_semester') }}</h1>
<p class="spims-text-dim">{{ $semester->academicYear?->name }}</p>
<x-card variant="panel" tag="form" method="POST" action="{{ route('admin.semesters.update', $semester) }}">
    @csrf
    @method('PUT')
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">{{ __('academics.name') }}</label>
            <input name="name" class="form-control" value="{{ old('name', $semester->name) }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.start_date') }}</label>
            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $semester->start_date?->toDateString()) }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.end_date') }}</label>
            <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $semester->end_date?->toDateString()) }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.registration_start') }}</label>
            <input type="date" name="registration_start" class="form-control" value="{{ old('registration_start', $semester->registration_start?->toDateString()) }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.registration_end') }}</label>
            <input type="date" name="registration_end" class="form-control" value="{{ old('registration_end', $semester->registration_end?->toDateString()) }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.status') }}</label>
            <select name="status" class="form-select" required>
                @foreach($statuses as $status)
                    @php $semStatusVal = $status->value; @endphp
                    <option value="{{ $semStatusVal }}" @selected(old('status', $semester->status->value) === $semStatusVal)>{{ $semStatusVal }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.add_drop_week') }}</label>
            <input type="number" name="add_drop_end_week" class="form-control" value="{{ old('add_drop_end_week', $semester->add_drop_end_week) }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.withdrawal_week') }}</label>
            <input type="number" name="last_withdrawal_week" class="form-control" value="{{ old('last_withdrawal_week', $semester->last_withdrawal_week) }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('offerings.withdrawal_refund') }}</label>
            <input type="number" step="0.01" name="withdrawal_refund_percent" class="form-control" value="{{ old('withdrawal_refund_percent', $semester->withdrawal_refund_percent) }}">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">{{ __('ui.save_changes') }}</button>
            <a href="{{ route('admin.semesters.index') }}" class="btn btn-outline-secondary">{{ __('ui.cancel') }}</a>
        </div>
    </div>
</x-card>
@endsection
