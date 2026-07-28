@extends('layouts.app')
@section('title', $assessment->title)
@section('content')
<h1 class="spims-title mb-3">{{ $assessment->title }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<p>{{ $assessment->mode->value }} · {{ $assessment->time_limit_minutes }} min · attempts {{ $assessment->attempts_allowed }}</p>
<form method="POST" action="{{ route('assessments.start', $assessment) }}">@csrf
    <button class="btn btn-primary">{{ __('assessment.start') }}</button>
</form>
<ul class="mt-3">
@foreach($attempts as $attempt)
    <li>#{{ $attempt->attempt_no }} — {{ $attempt->status->value }} — {{ $attempt->total_score }}</li>
@endforeach
</ul>
@endsection
