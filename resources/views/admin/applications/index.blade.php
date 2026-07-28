@extends('layouts.app')
@section('title', __('ui.nav_applications'))
@section('content')
<h1 class="spims-title mb-3">{{ __('ui.nav_applications') }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card border-0 shadow-sm">
    <table class="table mb-0">
        <thead><tr><th>{{ __('ui.email') }}</th><th>{{ __('academics.code') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($applications as $application)
            <tr>
                <td>{{ $application->applicant->email }}</td>
                <td>{{ $application->program->code }}</td>
                <td>{{ $application->status->value }}</td>
                <td><a href="{{ route('admin.applications.show', $application) }}">{{ __('admissions.review') }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $applications->links() }}
@endsection
