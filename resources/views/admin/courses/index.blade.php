@extends('layouts.app')
@section('title', __('ui.nav_courses'))
@section('content')
<div class="admin-console animate-in">
    <x-page-header :title="__('ui.nav_courses')" :subtitle="__('hubs.courses_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.courses.create') }}" class="btn btn-primary">{{ __('academics.create_course') }}</a>
        </x-slot:actions>
    </x-page-header>
    @if(session('status'))<div class="alert alert-success academic-alert">{{ session('status') }}</div>@endif
    <div class="spims-data-panel">
        <div class="spims-table-wrap spims-table-wrap--cards">
            <table class="table mb-0">
                <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('academics.title') }}</th><th>{{ __('academics.credits') }}</th><th>{{ __('academics.interest') }}</th></tr></thead>
                <tbody>
                @forelse($courses as $course)
                    <tr>
                        <td data-label="{{ __('academics.code') }}">
                            <a href="{{ route('admin.courses.show', $course) }}">{{ $course->code }}</a>
                            <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-sm btn-link">{{ __('ui.edit') }}</a>
                        </td>
                        <td data-label="{{ __('academics.title') }}">{{ $course->title }}</td>
                        <td data-label="{{ __('academics.credits') }}">{{ $course->credit_hours }}</td>
                        <td data-label="{{ __('academics.interest') }}">{{ $course->interest_flags_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state :title="__('ui.no_results')" />
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $courses->links() }}
</div>
@endsection
