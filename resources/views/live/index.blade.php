@extends('layouts.app')
@section('title', __('live.live_sessions'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <h1 class="spims-title mb-0">{{ __('live.live_sessions') }}</h1>
    <a href="{{ route('live-quiz.join') }}" class="btn btn-outline-primary">{{ __('live.live_quiz') }}</a>
</div>
<table class="table">
    <thead><tr><th>Title</th><th>Course</th><th>Start</th><th></th></tr></thead>
    <tbody>
    @foreach($sessions as $session)
        <tr>
            <td>{{ $session->title }}</td>
            <td>{{ $session->offering->course->code }}</td>
            <td>{{ $session->scheduled_start }}</td>
            <td>
                <form method="POST" action="{{ route('live.join', $session) }}">@csrf
                    <button class="btn btn-sm btn-primary">{{ __('live.join') }}</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
