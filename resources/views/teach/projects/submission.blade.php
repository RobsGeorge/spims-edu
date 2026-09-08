@extends('layouts.app')
@section('title', __('staff.projects.review').' — '.$submission->deliverable?->title)
@section('content')
<x-page-header
    :title="$submission->deliverable?->title"
    :subtitle="$submission->project?->name"
    :eyebrow="__('staff.projects.review')"
>
    <x-slot:actions>
        <a href="{{ route('teach.projects.show', [$offering, $assessment]) }}" class="btn btn-outline-secondary btn-sm">{{ __('staff.projects.back_board') }}</a>
    </x-slot:actions>
</x-page-header>

@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'projects', 'prefix' => 'teach'])

<div class="border rounded-3 p-3 mb-3 mt-3">
    <x-status-badge :status="$submission->review_status->value" :label="$submission->review_status->value" />
    @if($submission->late)
        <x-status-badge status="warning" :label="__('staff.projects.late')" />
    @endif
    @if($submission->body)
        <p class="mt-3 mb-0">{{ $submission->body }}</p>
    @endif
    @if($submission->link)
        <p class="mt-3 mb-0"><a href="{{ $submission->link }}">{{ $submission->link }}</a></p>
    @endif
    @foreach($submission->files as $file)
        <div class="small spims-text-dim">{{ $file->original_name }} ({{ $file->size_bytes }})</div>
    @endforeach
</div>

<form method="POST" action="{{ route('teach.projects.submissions.review', [$offering, $assessment, $submission]) }}" class="row g-2">
    @csrf
    <div class="col-md-6">
        <select name="review_status" class="form-select" required>
            @foreach($reviewStatuses as $status)
                @php $statusVal = $status->value; @endphp
                <option value="{{ $statusVal }}" @selected($submission->review_status === $status)>{{ __('staff.projects.review_'.$statusVal) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6"><button class="btn btn-primary">{{ __('staff.projects.save_review') }}</button></div>
</form>
@endsection
