@extends('layouts.app')
@section('title', __('teach.assignments_dashboard'))
@section('content')
<x-page-header
    :title="__('teach.assignments_dashboard')"
    :subtitle="__('teach.assignments_dashboard_sub')"
    :eyebrow="$offering->course->code"
>
    <x-slot:actions>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'assignments']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@forelse($stats as $row)
    <div class="border rounded-3 p-3 mb-3">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <h3 class="h6 mb-2">{{ $row['title'] }} <span class="badge bg-secondary">{{ $row['delivery_mode'] }}</span></h3>
        </div>
        <div class="d-flex flex-wrap gap-3 mb-3 small text-muted-theme">
            <span>{{ __('teach.assignments_enrolled') }}: {{ $row['enrolled_count'] }}</span>
            <span>{{ __('teach.assignments_submitted') }}: {{ $row['submitted_count'] }}</span>
            <span>{{ __('teach.assignments_ungraded') }}: {{ $row['ungraded_count'] }}</span>
            <span>{{ __('teach.assignments_overdue') }}: {{ $row['overdue_count'] }}</span>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if($row['delivery_mode'] !== 'OFFLINE')
                <form method="POST" action="{{ route('teach.assignments.remind', ['offering' => $offering, 'assignment' => $row['assignment_id']]) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary">{{ __('teach.assignments_remind') }}</button>
                </form>
            @endif
        </div>

        @if($row['delivery_mode'] === 'OFFLINE')
            <div class="row g-2 mt-2">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('teach.assignments.mark-received', ['offering' => $offering, 'assignment' => $row['assignment_id']]) }}" class="row g-2">
                        @csrf
                        <label class="form-text">{{ __('teach.assignments_mark_received_label') }}</label>
                        <div class="col-8"><input name="student_id" class="form-control form-control-sm" required></div>
                        <div class="col-4"><button class="btn btn-sm btn-outline-secondary w-100">{{ __('teach.assignments_mark_received') }}</button></div>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="POST" action="{{ route('teach.assignments.bulk-grade', ['offering' => $offering, 'assignment' => $row['assignment_id']]) }}">
                        @csrf
                        <label class="form-text">{{ __('teach.assignments_bulk_grade_label') }}</label>
                        <textarea name="grades" class="form-control form-control-sm mb-1" rows="2" placeholder="student_id,score,feedback"></textarea>
                        <button class="btn btn-sm btn-outline-secondary w-100">{{ __('teach.assignments_bulk_grade') }}</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
@empty
    <x-empty-state :title="__('teach.assignments_empty')" icon="bi-journal-text" />
@endforelse
@endsection
