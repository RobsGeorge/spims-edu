@extends('layouts.app')
@section('title', __('staff.events.title'))
@section('content')
<x-page-header
    :title="__('staff.events.title')"
    :subtitle="__('staff.events.subtitle')"
    :eyebrow="__('staff.events.eyebrow')"
/>

<form method="POST" action="{{ route('admin.events.store') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-12 col-md-4"><input name="title" class="form-control" required placeholder="{{ __('staff.events.title_placeholder') }}"></div>
    <div class="col-12 col-md-3"><input type="datetime-local" name="starts_at" class="form-control" required aria-label="{{ __('staff.events.starts_at') }}"></div>
    <div class="col-12 col-md-3"><input type="datetime-local" name="ends_at" class="form-control" required aria-label="{{ __('staff.events.ends_at') }}"></div>
    <div class="col-12 col-md-2"><input name="venue" class="form-control" placeholder="{{ __('staff.events.venue') }}"></div>
    <div class="col-12 col-md-2"><input type="number" name="capacity" class="form-control" min="1" placeholder="{{ __('staff.events.capacity') }}"></div>
    <div class="col-12 col-md-8"><input name="description" class="form-control" placeholder="{{ __('staff.events.description') }}"></div>
    <div class="col-12 col-md-2">
        <label class="form-check mt-2">
            <input type="checkbox" name="waitlist_enabled" value="1" class="form-check-input">
            <span class="form-check-label">{{ __('staff.events.waitlist') }}</span>
        </label>
    </div>
    <div class="col-12 col-md-2"><button class="btn btn-primary w-100">{{ __('staff.events.create') }}</button></div>
</form>

@forelse($events as $event)
    <div class="spims-staff-row border rounded-3 p-3 mb-2">
        <div>
            <strong>{{ $event->title }}</strong>
            <div class="small text-muted-theme">
                <x-status-badge :status="$event->status->value" :label="__('staff.events.status_'.$event->status->value)" />
                {{ $event->starts_at }} · {{ $event->venue }}
            </div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.events.show', $event) }}">{{ __('staff.events.manage') }}</a>
    </div>
@empty
    <x-empty-state :title="__('staff.events.empty')" icon="bi-calendar-event" />
@endforelse
@endsection
