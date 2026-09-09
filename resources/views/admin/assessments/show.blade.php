@extends('layouts.app')
@section('title', $assessment->title)
@section('content')
<h1 class="spims-title mb-3">{{ $assessment->title }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<p><x-badge :value="$assessment->mode" /> · {{ __('assessment.minutes_meta', ['count' => $assessment->time_limit_minutes]) }}</p>
<form method="POST" action="{{ route('admin.assessments.release', $assessment) }}" class="mb-3">@csrf<button class="btn btn-sm btn-success">{{ __('assessment.released') }}</button></form>
<form method="POST" action="{{ route('admin.assessments.announce', $assessment) }}" class="mb-3">@csrf<button class="btn btn-sm btn-primary">{{ __('assessment.results_announced') }}</button></form>
<form method="POST" action="{{ route('admin.assessments.attach', $assessment) }}" class="row g-2 mb-2">@csrf
    <div class="col-auto"><input name="question_id" class="form-control" placeholder="{{ __('assessment.question_ulid') }}" required aria-label="{{ __('assessment.question_ulid') }}"></div>
    <div class="col-auto"><button class="btn btn-outline-primary">{{ __('assessment.attach') }}</button></div>
</form>
<p class="form-text mb-4">{{ __('assessment.question_ulid_hint') }}</p>
<h2 class="h6">{{ __('assessment.questions') }}</h2>
<ul>@foreach($assessment->assessmentQuestions as $aq)<li>{{ $aq->question->prompt }} ({{ $aq->points() }})</li>@endforeach</ul>
<h2 class="h6">{{ __('assessment.attempts') }}</h2>
<ul>@foreach($assessment->attempts as $a)<li>{{ $a->student->email }} #{{ $a->attempt_no }} <x-badge :value="$a->status" /> {{ $a->total_score }} @if($a->terminated_for_cheating)<span class="badge bg-danger">{{ __('assessment.terminated') }}</span>@endif <a href="{{ route('admin.attempts.proctor', $a) }}">{{ __('assessment.proctor_warnings') }}</a></li>@endforeach</ul>
@endsection
