@extends('layouts.app')
@section('title', __('ui.nav_my_applications'))
@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_my_applications') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('admissions.start_application') }}</h2>
        <ul class="mb-0">
        @foreach($programs as $program)
            @foreach($program->applicationForms as $form)
                <li><a href="{{ route('applications.create', $form) }}">{{ $program->code }} — {{ $form->name }}</a></li>
            @endforeach
        @endforeach
        </ul>
    </div>
</div>
<ul>
@foreach($applications as $application)
    <li>{{ $application->program->code }} — {{ $application->status->value }}</li>
@endforeach
</ul>
@endsection
