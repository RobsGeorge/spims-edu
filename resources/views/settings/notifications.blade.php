@extends('layouts.app')
@section('title', __('communications.preferences_title'))
@section('content')
<x-page-header :title="__('communications.preferences_title')" :subtitle="__('communications.preferences_sub')" />

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('settings.notifications.update') }}" class="app-card p-4 mb-4">
    @csrf
    @method('PUT')
    @php $i = 0; @endphp
    @foreach($eventKeys as $eventKey)
        <div class="mb-3">
            <div class="fw-semibold mb-2">{{ __('communications.event_'.$eventKey) }}</div>
            @foreach(['in_app','mail','whatsapp'] as $channel)
                <label class="form-check form-check-inline">
                    <input type="hidden" name="preferences[{{ $i }}][event_key]" value="{{ $eventKey }}">
                    <input type="hidden" name="preferences[{{ $i }}][channel]" value="{{ $channel }}">
                    <input type="checkbox" name="preferences[{{ $i }}][enabled]" value="1" class="form-check-input"
                           @checked($matrix[$eventKey][$channel] ?? false)>
                    <span class="form-check-label">{{ __('communications.channel_'.$channel) }}</span>
                </label>
                @php $i++; @endphp
            @endforeach
        </div>
    @endforeach
    <button class="btn btn-primary">{{ __('ui.save') }}</button>
</form>

<x-page-header :title="__('communications.reminders_title')" :subtitle="__('communications.reminders_sub')" />

<form method="POST" action="{{ route('settings.reminders.store') }}" class="app-card p-4 mb-4">
    @csrf
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label">{{ __('communications.reminder_subject_type') }}</label>
            <input name="subject_type" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('communications.reminder_subject_id') }}</label>
            <input name="subject_id" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">{{ __('communications.remind_at') }}</label>
            <input type="datetime-local" name="remind_at" class="form-control" required>
        </div>
        <div class="col-12">
            <button class="btn btn-outline-primary">{{ __('ui.save') }}</button>
        </div>
    </div>
</form>

@forelse($reminders as $reminder)
    <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2">
        <div>
            <div>{{ $reminder->subject_type }} · {{ $reminder->subject_id }}</div>
            <div class="small spims-text-dim">{{ $reminder->remind_at }}</div>
        </div>
        <form method="POST" action="{{ route('settings.reminders.cancel', $reminder) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-outline-secondary">{{ __('communications.cancel') }}</button>
        </form>
    </div>
@empty
    <p class="spims-text-dim">{{ __('communications.no_reminders') }}</p>
@endforelse
@endsection
