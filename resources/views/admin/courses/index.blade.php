@extends('layouts.app')
@section('title', __('ui.nav_courses'))
@section('content')
<x-page-header :title="__('ui.nav_courses')">
    <x-slot:actions>
        <a href="{{ route('admin.courses.create') }}" class="btn btn-primary">
            <x-icon name="add" /> {{ __('academics.create_course') }}
        </a>
    </x-slot:actions>
</x-page-header>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<x-card variant="panel">
    <div class="table-responsive spims-table-wrap">
        <table class="table mb-0">
            <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('academics.title') }}</th><th>{{ __('academics.credits') }}</th><th>{{ __('academics.interest') }}</th></tr></thead>
            <tbody>
            @foreach($courses as $course)
                <tr>
                    <td>
                        <a href="{{ route('admin.courses.show', $course) }}">{{ $course->code }}</a>
                        <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-sm btn-link">{{ __('ui.edit') }}</a>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <x-course-cover :course="$course" class="bento-course-thumb" />
                            <span>{{ $course->title }}</span>
                        </div>
                    </td>
                    <td>{{ $course->credit_hours }}</td>
                    <td>{{ $course->interest_flags_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-card>
{{ $courses->links() }}
@endsection
