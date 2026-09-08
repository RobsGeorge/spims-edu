@extends('layouts.app')
@section('title', $assessment->title)
@section('content')
<h1 class="spims-title mb-3">{{ $assessment->title }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<p><x-badge :value="$assessment->mode" /> · {{ $assessment->time_limit_minutes }} {{ __('assessment.minutes') }} · {{ __('assessment.attempts') }} {{ $assessment->attempts_allowed }}</p>
<form method="POST" action="{{ route('assessments.start', $assessment) }}">@csrf
    <button class="btn btn-primary">{{ __('assessment.start') }}</button>
</form>

@if(! $showScores)
    <p class="alert alert-info mt-3" role="status">{{ __('assessment.results_hidden') }}</p>
@endif

<ul class="mt-3 list-unstyled">
@forelse($attempts as $attempt)
    <li class="app-card p-3 mb-2">
        <p class="mb-1">#{{ $attempt->attempt_no }} — <x-badge :value="$attempt->status" />
            @if($showScores)
                — {{ __('assessment.total_score') }}: {{ $attempt->total_score }}
            @endif
        </p>
        @if($showAnswers)
            <ul class="small mb-0">
                @forelse($attempt->answers as $answer)
                    @php $question = $answer->question; @endphp
                    <li class="mb-2">
                        <strong>{{ $question?->prompt }}</strong>
                        <div>{{ __('assessment.your_answer') }}: {{ \App\Support\ExamAnswerFormatter::studentResponse($answer) }}</div>
                        <div>{{ __('assessment.correct_answer') }}: {{ \App\Support\ExamAnswerFormatter::correctAnswer($question) }}</div>
                        @if($answer->final_score !== null)
                            <div>{{ __('assessment.final_score') }}: {{ $answer->final_score }}</div>
                        @endif
                    </li>
                @empty
                    <li>{{ __('assessment.no_answers') }}</li>
                @endforelse
            </ul>
        @endif
    </li>
@empty
    <li class="spims-text-dim">{{ __('assessment.attempts_empty') }}</li>
@endforelse
</ul>
@endsection
