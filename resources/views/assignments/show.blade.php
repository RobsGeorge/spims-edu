@extends('layouts.app')
@section('title', __('assessment.assignment_title'))
@section('content')
<h1 class="spims-title mb-3">{{ __('assessment.assignment_title') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@error('file_url')<div class="alert alert-danger">{{ $message }}</div>@enderror
@error('file')<div class="alert alert-danger">{{ $message }}</div>@enderror
@error('assignment')<div class="alert alert-danger">{{ $message }}</div>@enderror

<p>{{ $assignment->instructions }}</p>
<p>
    {{ __('assessment.due_date_label') }}: {{ $assignment->due_date }}
    · {{ __('assessment.max_points_label') }}: {{ $assignment->max_points }}
</p>
@if($assignment->allowed_file_types)
    <p class="text-muted-theme">{{ __('assessment.allowed_types', ['types' => implode(', ', $assignment->allowed_file_types)]) }}</p>
@endif

@if($submission)
    <p>
        {{ __('assessment.submitted_on', ['when' => $submission->submitted_at]) }}
        @if($submission->is_late)({{ __('assessment.late_flag') }})@endif
        — {{ __('assessment.score_label') }} {{ $submission->final_score }}
    </p>
    @if(!empty($submissionFileUrl))
        <p><a href="{{ $submissionFileUrl }}" target="_blank" rel="noopener">{{ __('assessment.current_file') }}</a></p>
    @endif
    @if($submission->received_at)
        <p class="text-success">{{ __('assessment.marked_received') }} ({{ $submission->received_at }})</p>
    @endif
@endif

@if($assignment->delivery_mode->value === 'OFFLINE')
    <p class="text-muted-theme">{{ __('assessment.offline_use_mark_received') }}</p>
@else
    <form method="POST" action="{{ route('assignments.submit', $assignment) }}" enctype="multipart/form-data">
        @csrf
        @if($assignment->submission_type->value !== 'FILE')
            <label class="form-label" for="assignment-text">{{ __('assessment.text_body_label') }}</label>
            <textarea id="assignment-text" name="text_body" class="form-control mb-2" rows="5"></textarea>
        @endif
        @if($assignment->submission_type->value !== 'TEXT')
            <label class="form-label" for="assignment-file">{{ __('assessment.file_label') }}</label>
            <input id="assignment-file" type="file" name="file" class="form-control mb-2">
            <p class="small text-muted-theme">{{ __('assessment.file_help') }}</p>
        @endif
        <button class="btn btn-primary" type="submit">{{ __('assessment.submit_work') }}</button>
    </form>
@endif
@endsection
