@extends('layouts.app')
@section('title', $event->title)
@section('content')
<x-page-header
    :title="$event->title"
    :subtitle="$event->venue"
    :eyebrow="__('staff.events.eyebrow')"
>
    <x-slot:actions>
        <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('staff.events.back_list') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="d-flex flex-wrap gap-2 mb-3">
    <x-status-badge :status="$event->status->value" :label="__('staff.events.status_'.$event->status->value)" />
    @if($event->status->value === 'DRAFT')
        <form method="POST" action="{{ route('admin.events.publish', $event) }}">
            @csrf
            <button class="btn btn-sm btn-primary">{{ __('staff.events.publish') }}</button>
        </form>
    @endif
    @if($event->status->value !== 'CANCELLED')
        <form method="POST" action="{{ route('admin.events.cancel', $event) }}">
            @csrf
            <button class="btn btn-sm btn-outline-danger">{{ __('staff.events.cancel') }}</button>
        </form>
    @endif
</div>

@if($event->status->value === 'DRAFT')
    <form method="POST" action="{{ route('admin.events.update', $event) }}" class="row g-2 mb-4">
        @csrf
        <div class="col-md-4"><input name="title" class="form-control" required value="{{ $event->title }}"></div>
        <div class="col-md-3"><input type="datetime-local" name="starts_at" class="form-control" required value="{{ optional($event->starts_at)->format('Y-m-d\TH:i') }}"></div>
        <div class="col-md-3"><input type="datetime-local" name="ends_at" class="form-control" required value="{{ optional($event->ends_at)->format('Y-m-d\TH:i') }}"></div>
        <div class="col-md-2"><input name="venue" class="form-control" value="{{ $event->venue }}" placeholder="{{ __('staff.events.venue') }}"></div>
        <div class="col-md-2"><input type="number" name="capacity" class="form-control" min="1" value="{{ $event->capacity }}" placeholder="{{ __('staff.events.capacity') }}"></div>
        <div class="col-md-8"><input name="description" class="form-control" value="{{ $event->description }}" placeholder="{{ __('staff.events.description') }}"></div>
        <div class="col-md-2">
            <label class="form-check mt-2">
                <input type="checkbox" name="waitlist_enabled" value="1" class="form-check-input" @checked($event->waitlist_enabled)>
                <span class="form-check-label">{{ __('staff.events.waitlist') }}</span>
            </label>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">{{ __('ui.save') }}</button></div>
    </form>
@endif

<h2 class="h6">{{ __('staff.events.check_in') }}</h2>
<form method="POST" action="{{ route('admin.events.check-in', $event) }}" class="row g-2 mb-4">
    @csrf
    <div class="col-md-8"><input name="payload" class="form-control" required placeholder="{{ __('staff.events.token_placeholder') }}" autocomplete="off"></div>
    <div class="col-md-4"><button class="btn btn-primary w-100">{{ __('staff.events.verify') }}</button></div>
</form>

<h2 class="h6">{{ __('staff.events.reservations') }}</h2>
@forelse($reserved as $reservation)
    <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2">
        <div>
            <strong>{{ $reservation->student?->first_name }} {{ $reservation->student?->last_name }}</strong>
            <div class="small text-muted-theme">{{ $reservation->student?->email }}</div>
        </div>
        <div>
            <x-status-badge status="success" :label="__('staff.events.status_RESERVED')" />
            @if($reservation->checkIn)
                <x-status-badge status="info" :label="__('staff.events.checked_in_badge')" />
            @endif
        </div>
    </div>
@empty
    <x-empty-state :title="__('staff.events.no_reservations')" icon="bi-people" />
@endforelse

<h2 class="h6 mt-4">{{ __('staff.events.waitlist_heading') }}</h2>
@forelse($waitlist as $reservation)
    <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2">
        <strong>{{ $reservation->student?->first_name }} {{ $reservation->student?->last_name }}</strong>
        <x-status-badge status="warning" :label="__('staff.events.status_WAITLISTED')" />
    </div>
@empty
    <x-empty-state :title="__('staff.events.no_waitlist')" icon="bi-hourglass" />
@endforelse

<h2 class="h6 mt-4">{{ __('staff.events.exceptions') }}</h2>
<form method="POST" action="{{ route('admin.events.exceptions.store', $event) }}" class="row g-2 mb-3">
    @csrf
    <div class="col-md-5"><input name="student_id" class="form-control" required placeholder="{{ __('staff.events.student_id') }}"></div>
    <div class="col-md-3">
        <select name="kind" class="form-select" aria-label="{{ __('staff.events.exception_kind') }}">
            @foreach($exceptionKinds as $kind)
                <option value="{{ $kind->value }}">{{ __('staff.events.exception_'.$kind->value) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4"><button class="btn btn-outline-primary w-100">{{ __('staff.events.save_exception') }}</button></div>
</form>
@foreach($event->exceptions as $exception)
    <div class="border rounded-3 p-2 mb-2">
        {{ $exception->student?->email ?? $exception->student_id }}
        <x-status-badge :status="$exception->kind->value === 'ALLOW' ? 'success' : 'danger'" :label="__('staff.events.exception_'.$exception->kind->value)" />
    </div>
@endforeach
@endsection
