@extends('layouts.app')
@section('title', __('enrollment.degree_audit'))
@section('content')
<h1 class="spims-title">{{ __('enrollment.degree_audit') }} — {{ $audit['program'] }}</h1>
<p>{{ __('enrollment.required_progress') }}: {{ $audit['required_met'] }} / {{ $audit['required_total'] }}</p>
<p>{{ __('enrollment.elective_progress') }}: {{ $audit['elective_credits_met'] }} / {{ $audit['elective_credits_required'] }}</p>
<h2 class="h6">{{ __('enrollment.remaining') }}</h2>
<ul>
@forelse($audit['remaining'] as $course)
    <li>{{ $course['code'] }} — {{ $course['title'] }} ({{ $course['requirement'] }})</li>
@empty
    <li>{{ __('enrollment.complete') }}</li>
@endforelse
</ul>
@endsection
