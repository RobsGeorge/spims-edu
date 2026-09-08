@extends('layouts.app')
@section('title', __('live.live_sessions').' — '.$offering->course->code)
@section('content')
<x-page-header
    :title="__('live.live_sessions')"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
    :eyebrow="__('teach.workspace')"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'live', 'prefix' => 'teach'])

@if(session('status'))
    <div class="alert alert-success mt-3">{{ session('status') }}</div>
@endif

@php
    $agenda = $sessions->groupBy(fn ($session) => optional($session->scheduled_start)->toDateString() ?? 'unscheduled');
@endphp

<section class="mb-4 mt-3" aria-labelledby="live-agenda-heading">
    <h2 id="live-agenda-heading" class="h5 mb-3">{{ __('live.agenda') }}</h2>
    @if($agenda->isEmpty())
        <x-empty-state :title="__('live.agenda_empty')" />
    @else
        @foreach($agenda as $date => $daySessions)
            <div class="mb-3">
                <h3 class="h6 text-muted-theme mb-2">
                    {{ $date === 'unscheduled' ? __('live.unscheduled') : \Illuminate\Support\Carbon::parse($date)->toFormattedDateString() }}
                </h3>
                <ul class="list-unstyled spims-live-agenda mb-0">
                    @foreach($daySessions as $session)
                        <li class="d-flex flex-wrap gap-2 align-items-baseline py-2 border-bottom border-opacity-25">
                            <span class="fw-semibold">{{ optional($session->scheduled_start)->format('H:i') }}</span>
                            <span>{{ $session->title }}</span>
                            <span class="text-muted-theme small">({{ $session->duration_minutes }}{{ __('live.minutes_abbr') }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
</section>

@forelse($sessions as $session)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6">{{ $session->title }} — {{ $session->scheduled_start }} ({{ $session->duration_minutes }}{{ __('live.minutes_abbr') }})</h2>
        <p class="small mb-2">{{ __('live.zoom_meeting') }}: {{ $session->zoom_meeting_id }}
            @if($session->recording_url)
                · {{ __('live.recording') }}: {{ $session->recording_url }}
            @endif
        </p>
        <ul class="small">
            @forelse($session->attendance as $row)
                <li>{{ $row->student->email }} — {{ $row->status->value }} ({{ $row->minutes_attended }}{{ __('live.minutes_abbr') }})</li>
            @empty
                <li class="text-muted-theme">{{ __('live.no_attendance_yet') }}</li>
            @endforelse
        </ul>

        <h3 class="h6 mt-3">{{ __('live.import_attendance') }}</h3>
        <p class="small text-muted-theme">{{ __('live.import_help') }}</p>
        <form method="POST" action="{{ route('teach.live.attendance.import', [$offering, $session]) }}" class="row g-2">
            @csrf
            <div class="col-12">
                <label class="form-label" for="participants_json_{{ $session->id }}">{{ __('live.participants_json') }}</label>
                <textarea
                    id="participants_json_{{ $session->id }}"
                    name="participants_json"
                    class="form-control font-monospace"
                    rows="4"
                    placeholder='[{"email":"student@example.com","minutes":80}]'
                ></textarea>
                <div class="form-text">{{ __('live.participants_json_help') }}</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="participants_rows_{{ $session->id }}">{{ __('live.participants_rows') }}</label>
                <textarea
                    id="participants_rows_{{ $session->id }}"
                    name="participants_rows"
                    class="form-control font-monospace"
                    rows="3"
                    placeholder="{{ __('live.participants_rows_placeholder') }}"
                ></textarea>
                <div class="form-text">{{ __('live.participants_rows_help') }}</div>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label" for="participant_email_{{ $session->id }}">{{ __('live.participant_email') }}</label>
                <input id="participant_email_{{ $session->id }}" name="participants[0][email]" type="email" class="form-control" autocomplete="off">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="participant_user_{{ $session->id }}">{{ __('live.participant_user_id') }}</label>
                <input id="participant_user_{{ $session->id }}" name="participants[0][user_id]" class="form-control" autocomplete="off">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="participant_minutes_{{ $session->id }}">{{ __('live.participant_minutes') }}</label>
                <input id="participant_minutes_{{ $session->id }}" name="participants[0][minutes]" type="number" min="0" class="form-control">
            </div>
            <div class="col-12">
                <button class="btn btn-primary">{{ __('live.import') }}</button>
            </div>
        </form>
    </div>
</div>
@empty
    <x-empty-state :title="__('live.agenda_empty')" icon="bi-camera-video" />
@endforelse
@endsection
