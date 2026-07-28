@extends('layouts.app')
@section('title', $assessment->title)
@section('content')
<h1 class="spims-title mb-3">{{ $assessment->title }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<p>{{ $assessment->mode->value }} · {{ $assessment->time_limit_minutes }} min</p>
<form method="POST" action="{{ route('admin.assessments.release', $assessment) }}" class="mb-3">@csrf<button class="btn btn-sm btn-success">{{ __('assessment.released') }}</button></form>
<form method="POST" action="{{ route('admin.assessments.attach', $assessment) }}" class="row g-2 mb-4">@csrf
    <div class="col-auto"><input name="question_id" class="form-control" placeholder="question ULID" required></div>
    <div class="col-auto"><button class="btn btn-outline-primary">Attach</button></div>
</form>
<h2 class="h6">Questions</h2>
<ul>@foreach($assessment->assessmentQuestions as $aq)<li>{{ $aq->question->prompt }} ({{ $aq->points() }})</li>@endforeach</ul>
<h2 class="h6">Attempts</h2>
<ul>@foreach($assessment->attempts as $a)<li>{{ $a->student->email }} #{{ $a->attempt_no }} {{ $a->status->value }} {{ $a->total_score }}</li>@endforeach</ul>
@endsection
