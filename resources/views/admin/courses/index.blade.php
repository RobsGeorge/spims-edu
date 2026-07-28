@extends('layouts.app')
@section('title', __('ui.nav_courses'))
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="spims-title mb-0">{{ __('ui.nav_courses') }}</h1>
    <a href="{{ route('admin.courses.create') }}" class="btn btn-primary">{{ __('academics.create_course') }}</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('academics.title') }}</th><th>{{ __('academics.credits') }}</th><th>{{ __('academics.interest') }}</th></tr></thead>
            <tbody>
            @foreach($courses as $course)
                <tr>
                    <td><a href="{{ route('admin.courses.show', $course) }}">{{ $course->code }}</a></td>
                    <td>{{ $course->title }}</td>
                    <td>{{ $course->credit_hours }}</td>
                    <td>{{ $course->interest_flags_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
{{ $courses->links() }}
@endsection
