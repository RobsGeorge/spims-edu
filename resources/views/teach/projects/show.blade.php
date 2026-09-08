@extends('layouts.app')
@section('title', $assessment->title)
@section('content')
<x-page-header
    :title="$assessment->title"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
    :eyebrow="__('staff.projects.seating')"
>
    <x-slot:actions>
        <a href="{{ route('teach.projects.index', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('staff.projects.back_list') }}</a>
        @if($assessment->status->value === 'DRAFT')
            <form method="POST" action="{{ route('teach.projects.publish', [$offering, $assessment]) }}">
                @csrf
                <button class="btn btn-sm btn-primary">{{ __('staff.projects.publish') }}</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'projects', 'prefix' => 'teach'])

<h2 class="h6 mt-3">{{ __('staff.projects.seating') }}</h2>
@forelse($seating as $row)
    @php $project = $row['project']; @endphp
    <div class="border rounded-3 p-3 mb-2">
        <div class="spims-staff-row">
            <strong>{{ $project->name }}</strong>
            <span class="small spims-text-dim">{{ $row['seats'] }}/{{ $row['capacity'] }}</span>
        </div>
        <ul class="mb-0 mt-2">
            @foreach($project->activeMemberships as $membership)
                <li>{{ $membership->student?->first_name }} {{ $membership->student?->last_name }}
                    <span class="small spims-text-dim">{{ $membership->student_id }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@empty
    <x-empty-state :title="__('staff.projects.no_teams')" icon="bi-diagram-3" />
@endforelse

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <h2 class="h6">{{ __('staff.projects.move') }}</h2>
        <form method="POST" action="{{ route('teach.projects.move', [$offering, $assessment]) }}" class="row g-2">
            @csrf
            <div class="col-12"><input name="student_id" class="form-control form-control-sm" required placeholder="{{ __('staff.projects.student_id') }}"></div>
            <div class="col-6"><input name="from_project_id" class="form-control form-control-sm" required placeholder="{{ __('staff.projects.from_team') }}"></div>
            <div class="col-6"><input name="to_project_id" class="form-control form-control-sm" required placeholder="{{ __('staff.projects.to_team') }}"></div>
            <div class="col-12"><button class="btn btn-sm btn-outline-primary">{{ __('staff.projects.move') }}</button></div>
        </form>
    </div>
    <div class="col-md-6">
        <h2 class="h6">{{ __('staff.projects.merge') }}</h2>
        <form method="POST" action="{{ route('teach.projects.merge', [$offering, $assessment]) }}" class="row g-2">
            @csrf
            <div class="col-6"><input name="source_project_id" class="form-control form-control-sm" required placeholder="{{ __('staff.projects.source_team') }}"></div>
            <div class="col-6"><input name="target_project_id" class="form-control form-control-sm" required placeholder="{{ __('staff.projects.target_team') }}"></div>
            <div class="col-12"><button class="btn btn-sm btn-outline-primary">{{ __('staff.projects.merge') }}</button></div>
        </form>
    </div>
</div>

<h2 class="h6 mt-4">{{ __('staff.projects.grades') }}</h2>
<form method="POST" action="{{ route('teach.projects.team-score', [$offering, $assessment]) }}" class="row g-2 mb-2">
    @csrf
    <div class="col-md-6"><input name="project_id" class="form-control" required placeholder="{{ __('staff.projects.team_id') }}"></div>
    <div class="col-md-4"><input type="number" step="0.01" name="score" class="form-control" required placeholder="{{ __('staff.projects.score') }}"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">{{ __('staff.projects.save_team') }}</button></div>
</form>
<form method="POST" action="{{ route('teach.projects.student-score', [$offering, $assessment]) }}" class="row g-2 mb-3">
    @csrf
    <div class="col-md-6"><input name="student_id" class="form-control" required placeholder="{{ __('staff.projects.student_id') }}"></div>
    <div class="col-md-4"><input type="number" step="0.01" name="score" class="form-control" required placeholder="{{ __('staff.projects.score') }}"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">{{ __('staff.projects.save_student') }}</button></div>
</form>

@if($confirmToken)
<form method="POST" action="{{ route('teach.projects.announce', [$offering, $assessment]) }}" id="announceGradesForm">
    @csrf
    <input type="hidden" name="confirmation_token" value="{{ $confirmToken }}">
</form>
<button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#announceGradesModal">{{ __('staff.projects.announce') }}</button>
<x-confirm-dialog
    id="announceGradesModal"
    :title="__('staff.projects.announce_title')"
    :message="__('staff.projects.announce_body', ['count' => $pendingGrades])"
    tone="danger"
>
    <x-slot:confirm>
        <button type="submit" form="announceGradesForm" class="btn btn-danger">{{ __('staff.projects.announce_confirm') }}</button>
    </x-slot:confirm>
</x-confirm-dialog>
@endif

<h2 class="h6 mt-4">{{ __('staff.projects.submissions') }}</h2>
@forelse($submissions as $submission)
    <div class="spims-staff-row border rounded-3 p-2 mb-2">
        <div>
            <strong>{{ $submission->deliverable?->title }}</strong>
            <div class="small spims-text-dim">{{ $submission->project?->name }} · <x-badge :value="$submission->review_status" /></div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('teach.projects.submissions.show', [$offering, $assessment, $submission]) }}">{{ __('staff.projects.review') }}</a>
    </div>
@empty
    <x-empty-state :title="__('staff.projects.no_submissions')" icon="bi-file-earmark" />
@endforelse

<h2 class="h6 mt-4">{{ __('staff.projects.peer') }}</h2>
@forelse($peerAggregates as $agg)
    <div class="border rounded-3 p-3 mb-2">
        <div>{{ __('staff.projects.peer_count', ['count' => $agg['count']]) }} · {{ __('staff.projects.peer_avg', ['avg' => $agg['average_score']]) }}</div>
        <div class="small spims-text-dim">{{ implode(', ', $agg['scores']) }}</div>
    </div>
@empty
    <x-empty-state :title="__('staff.projects.no_peer')" icon="bi-chat-square-text" />
@endforelse
@endsection
