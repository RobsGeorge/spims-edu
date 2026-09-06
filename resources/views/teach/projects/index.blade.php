@extends('layouts.app')
@section('title', __('staff.projects.title').' — '.$offering->course->code)
@section('content')
<x-page-header
    :title="__('staff.projects.title')"
    :subtitle="$offering->course->code.' — '.$offering->course->title"
    :eyebrow="__('teach.workspace')"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'projects']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'projects', 'prefix' => 'teach'])

<form method="POST" action="{{ route('teach.projects.store', $offering) }}" class="row g-2 mb-4 mt-3">
    @csrf
    <div class="col-md-4"><input name="title" class="form-control" required placeholder="{{ __('staff.projects.title_placeholder') }}"></div>
    <div class="col-md-2"><input type="number" name="team_size_min" class="form-control" value="1" min="1" aria-label="{{ __('staff.projects.team_min') }}"></div>
    <div class="col-md-2"><input type="number" name="team_size_max" class="form-control" value="4" min="1" aria-label="{{ __('staff.projects.team_max') }}"></div>
    <div class="col-md-2">
        <select name="grading_mode" class="form-select" aria-label="{{ __('staff.projects.grading_mode') }}">
            @foreach($gradingModes as $mode)
                <option value="{{ $mode->value }}">{{ __('staff.projects.mode_'.$mode->value) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('staff.projects.create') }}</button></div>
</form>

@forelse($assessments as $assessment)
    <div class="spims-staff-row border rounded-3 p-3 mb-2">
        <div>
            <strong>{{ $assessment->title }}</strong>
            <div class="small text-muted-theme">
                <x-status-badge :status="$assessment->status->value" :label="__('staff.projects.status_'.$assessment->status->value)" />
            </div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('teach.projects.show', [$offering, $assessment]) }}">{{ __('staff.projects.open') }}</a>
    </div>
@empty
    <x-empty-state :title="__('staff.projects.empty')" icon="bi-people" />
@endforelse
@endsection
