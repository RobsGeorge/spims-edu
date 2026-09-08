@extends('layouts.app')
@section('title', $assessment->title)
@section('content')
<x-page-header
    :title="$assessment->title"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
    :eyebrow="__('assessment.attempts')"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'assessments']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'assessments', 'prefix' => 'teach'])

@if(session('status'))
    <div class="alert alert-success mt-3">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<p class="mt-3 mb-2 small spims-text-dim">
    <x-badge :value="$assessment->mode" />
    @if($assessment->time_limit_minutes)
        · {{ $assessment->time_limit_minutes }} {{ __('assessment.minutes') }}
    @endif
    @if($announcement?->announced_at)
        · {{ __('assessment.results_announced') }}
    @endif
</p>

@if($confirmToken)
<form method="POST" action="{{ route('teach.assessments.announce', [$offering, $assessment]) }}" id="announceResultsForm">
    @csrf
    <input type="hidden" name="confirmation_token" value="{{ $confirmToken }}">
</form>
<button type="button" class="btn btn-danger btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#announceResultsModal">{{ __('assessment.announce_results') }}</button>
<x-confirm-dialog
    id="announceResultsModal"
    :title="__('assessment.announce_confirm_title')"
    :message="__('assessment.announce_confirm_body', ['title' => $assessment->title])"
    tone="danger"
>
    <x-slot:confirm>
        <button type="submit" form="announceResultsForm" class="btn btn-danger">{{ __('assessment.announce_confirm') }}</button>
    </x-slot:confirm>
</x-confirm-dialog>
@endif

<h2 class="h6 mt-3">{{ __('assessment.attempts') }}</h2>
@forelse($attempts as $attempt)
    @php
        $canGrade = ! $attempt->terminated_for_cheating
            && in_array($attempt->status, $gradeableStatuses, true);
    @endphp
    <article class="border rounded-3 p-3 mb-3">
        <div class="spims-staff-row">
            <div>
                <strong>{{ $attempt->student?->first_name }} {{ $attempt->student?->last_name }}</strong>
                <div class="small spims-text-dim">{{ $attempt->student?->email }} · {{ __('assessment.attempt_n', ['n' => $attempt->attempt_no]) }}</div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <x-status-badge :status="$attempt->status->value" :label="$attempt->status->value" />
                @if($attempt->total_score !== null)
                    <span class="small">{{ __('assessment.total_score') }}: {{ $attempt->total_score }}</span>
                @endif
                @if($attempt->terminated_for_cheating)
                    <span class="badge bg-danger">{{ __('assessment.terminated') }}</span>
                @endif
            </div>
        </div>

        @if($attempt->proctor_warnings || $attempt->proctorEvents->isNotEmpty())
            <p class="small spims-text-dim mt-2 mb-1">
                {{ __('assessment.proctor_warnings') }}: {{ $attempt->proctor_warnings }}
            </p>
            @if($attempt->proctorEvents->isNotEmpty())
                <ul class="small mb-2">
                    @foreach($attempt->proctorEvents as $event)
                        <li>{{ $event->event_type }}
                            @if($event->warning_number)
                                · #{{ $event->warning_number }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif

        @forelse($attempt->answers as $answer)
            @php $needsOverride = $answer->final_score === null; @endphp
            <div class="border rounded-3 p-2 mt-2">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-1">
                    <div>
                        <span class="small">{{ $answer->question?->prompt }}</span>
                        @if($needsOverride && $canGrade)
                            <span class="badge bg-warning text-dark">{{ __('assessment.needs_override') }}</span>
                        @endif
                    </div>
                    <div class="small spims-text-dim">
                        @if($answer->auto_score !== null)
                            {{ __('assessment.auto_score') }}: {{ $answer->auto_score }}
                        @endif
                        @if($answer->ai_suggested_score !== null)
                            · {{ __('assessment.ai_score') }}: {{ $answer->ai_suggested_score }}
                        @endif
                    </div>
                </div>
                @if(is_array($answer->response) && filled($answer->response['text'] ?? null))
                    <p class="small mb-2">{{ $answer->response['text'] }}</p>
                @endif
                @if($canGrade)
                    <form method="POST" action="{{ route('teach.assessments.grade', [$offering, $assessment, $answer]) }}" class="row g-2">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-text">{{ __('assessment.final_score') }}</label>
                            <input type="number" step="0.01" min="0" name="final_score" class="form-control form-control-sm" value="{{ $answer->final_score ?? $answer->ai_suggested_score }}" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-text">{{ __('assessment.feedback') }}</label>
                            <input name="feedback" class="form-control form-control-sm" value="{{ $answer->feedback }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-sm btn-outline-primary w-100">{{ __('assessment.override_score') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <p class="small spims-text-dim mb-0 mt-2">{{ __('assessment.no_answers') }}</p>
        @endforelse
    </article>
@empty
    <x-empty-state :title="__('assessment.attempts_empty')" icon="bi-journal-check" />
@endforelse
@endsection
