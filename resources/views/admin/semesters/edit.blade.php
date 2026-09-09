@extends('layouts.app')
@section('title', __('offerings.edit_semester'))
@section('content')

<x-page-header :title="__('offerings.edit_semester')" :subtitle="$semester->academicYear?->name" />

<x-card variant="panel" tag="form" method="POST" action="{{ route('admin.semesters.update', $semester) }}">
    @csrf
    @method('PUT')
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <x-field :label="__('academics.name')" name="name" required>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $semester->name) }}" required>
            </x-field>
        </div>
        <div class="col-12 col-md-4">
            <x-field :label="__('offerings.start_date')" name="start_date" required>
                <input type="date" name="start_date" class="form-control"
                    value="{{ old('start_date', $semester->start_date?->toDateString()) }}" required>
            </x-field>
        </div>
        <div class="col-12 col-md-4">
            <x-field :label="__('offerings.end_date')" name="end_date" required>
                <input type="date" name="end_date" class="form-control"
                    value="{{ old('end_date', $semester->end_date?->toDateString()) }}" required>
            </x-field>
        </div>
        <div class="col-12 col-md-4">
            <x-field :label="__('offerings.registration_start')" name="registration_start" required>
                <input type="date" name="registration_start" class="form-control"
                    value="{{ old('registration_start', $semester->registration_start?->toDateString()) }}" required>
            </x-field>
        </div>
        <div class="col-12 col-md-4">
            <x-field :label="__('offerings.registration_end')" name="registration_end" required>
                <input type="date" name="registration_end" class="form-control"
                    value="{{ old('registration_end', $semester->registration_end?->toDateString()) }}" required>
            </x-field>
        </div>
        <div class="col-12 col-md-4">
            @php $currentStatus = $semester->status->value; @endphp
            <x-field :label="__('offerings.status')" name="status" required>
                <select name="status" class="form-select" required>
                    @foreach($statuses as $status)
                        @php $statusVal = $status->value; @endphp
                        <option value="{{ $statusVal }}"
                            @selected(old('status', $currentStatus) === $statusVal)>
                            {{ __('semesters.status_' . strtolower($statusVal)) }}
                        </option>
                    @endforeach
                </select>
            </x-field>
        </div>
        <div class="col-12 col-md-4">
            <x-field :label="__('offerings.add_drop_week')" name="add_drop_end_week" required>
                <input type="number" name="add_drop_end_week" class="form-control"
                    value="{{ old('add_drop_end_week', $semester->add_drop_end_week) }}" required>
            </x-field>
        </div>
        <div class="col-12 col-md-4">
            <x-field :label="__('offerings.withdrawal_week')" name="last_withdrawal_week" required>
                <input type="number" name="last_withdrawal_week" class="form-control"
                    value="{{ old('last_withdrawal_week', $semester->last_withdrawal_week) }}" required>
            </x-field>
        </div>
        <div class="col-12 col-md-4">
            <x-field :label="__('offerings.withdrawal_refund')" name="withdrawal_refund_percent">
                <input type="number" step="0.01" name="withdrawal_refund_percent" class="form-control"
                    value="{{ old('withdrawal_refund_percent', $semester->withdrawal_refund_percent) }}">
            </x-field>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">{{ __('ui.save_changes') }}</button>
            <a href="{{ route('admin.semesters.index') }}" class="btn btn-outline-secondary">{{ __('ui.cancel') }}</a>
        </div>
    </div>
</x-card>

@endsection
