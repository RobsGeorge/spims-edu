@extends('layouts.app')
@section('title', __('enrollment.waitlist'))
@section('content')
<h1 class="spims-title">{{ __('enrollment.waitlist') }} — {{ $offering->course->code }}</h1>
<ol>
@foreach($waitlisted as $enrollment)
    <li>{{ $enrollment->student->email }} — {{ $enrollment->enrolled_at }}</li>
@endforeach
</ol>
@endsection
