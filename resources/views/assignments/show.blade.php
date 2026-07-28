@extends('layouts.app')
@section('title', __('assessment.assignment_submitted'))
@section('content')
<h1 class="spims-title mb-3">Assignment</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<p>{{ $assignment->instructions }}</p>
<p>Due: {{ $assignment->due_date }} · Max: {{ $assignment->max_points }}</p>
@if($submission)
    <p>Submitted {{ $submission->submitted_at }} @if($submission->is_late)(late)@endif — score {{ $submission->final_score }}</p>
@endif
<form method="POST" action="{{ route('assignments.submit', $assignment) }}">@csrf
    <textarea name="text_body" class="form-control mb-2" rows="5"></textarea>
    <input name="file_url" class="form-control mb-2" placeholder="file url">
    <button class="btn btn-primary">Submit</button>
</form>
@endsection
