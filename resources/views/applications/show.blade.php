@extends('layouts.app')
@section('title', $application->program->code.' — '.$application->form->name)
@section('content')
<x-page-header :title="$application->program->code.' — '.$application->form->name" :subtitle="$application->form->name">
    <x-slot:actions>
        <a href="{{ route('applications.index') }}" class="btn btn-outline-primary btn-sm">{{ __('ui.nav_my_applications') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success academic-alert">{{ session('status') }}</div>
@endif

<x-card variant="panel" class="mb-4">
    <p class="mb-3">
        <x-status-badge :status="$application->status->badgeTone()" :label="$application->status->label()" />
    </p>

    @if($application->status->isEditable())
        <p class="spims-text-dim mb-3">{{ __('admissions.continue_draft_help') }}</p>
    @elseif($application->status === \App\Enums\ApplicationStatus::UnderReview || $application->status === \App\Enums\ApplicationStatus::Submitted)
        <p class="mb-3">{{ __('admissions.waiting_review') }}</p>
    @elseif($application->status === \App\Enums\ApplicationStatus::Waitlisted)
        <p class="mb-3">{{ __('admissions.waiting_waitlist') }}</p>
    @elseif($application->status === \App\Enums\ApplicationStatus::Accepted)
        <p class="mb-3">{{ __('admissions.accepted_enroll_cue') }}</p>
    @elseif($application->status === \App\Enums\ApplicationStatus::Rejected)
        <p class="spims-text-dim mb-3">{{ __('admissions.rejected_help') }}</p>
    @endif

    @if($application->decision_note)
        <p class="mb-3"><strong>{{ __('admissions.decision_note') }}:</strong> {{ $application->decision_note }}</p>
    @endif

    <h2 class="h6 spims-title mb-3">{{ __('admissions.answers_heading') }}</h2>
    @if(count($answers) === 0)
        <p class="spims-text-dim mb-0">{{ __('admissions.answers_empty') }}</p>
    @else
        <dl class="mb-0">
            @foreach($answers as $answer)
                <div class="mb-3">
                    <dt class="spims-text-dim">{{ $answer['label'] }}</dt>
                    <dd class="mb-0">
                        @if($answer['display'] === null || $answer['display'] === '')
                            <span class="spims-text-dim">{{ __('admissions.answer_blank') }}</span>
                        @elseif($answer['is_file'])
                            {{ __('admissions.document') }}: {{ $answer['display'] }}
                        @else
                            {{ $answer['display'] }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif

    <div class="d-flex flex-wrap gap-2 mt-4">
        @if($application->status->isEditable())
            <a href="{{ route('applications.create', $application->form) }}" class="btn btn-primary">{{ __('admissions.continue_draft') }}</a>
        @endif
        @if($application->status === \App\Enums\ApplicationStatus::Accepted)
            <a href="{{ route('enrollments.index') }}" class="btn btn-primary">{{ __('enrollment.enroll_now') }}</a>
        @endif
        @if($application->status->isWithdrawable())
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#withdraw-application">
                {{ __('admissions.withdraw') }}
            </button>
        @endif
    </div>
</x-card>

@if($application->status->isWithdrawable())
    <form method="POST" action="{{ route('applications.withdraw', $application) }}" id="withdraw-application-form">
        @csrf
    </form>
    <x-confirm-dialog
        id="withdraw-application"
        :title="__('admissions.withdraw_confirm_title')"
        :message="__('admissions.withdraw_confirm_body')"
        tone="danger"
    >
        <x-slot:confirm>
            <button type="submit" form="withdraw-application-form" class="btn btn-danger">{{ __('admissions.withdraw') }}</button>
        </x-slot:confirm>
    </x-confirm-dialog>
@endif
@endsection
