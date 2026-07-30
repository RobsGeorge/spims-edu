@extends('layouts.app')

@section('title', __('ui.nav_applications'))

@section('content')
<x-page-header :title="__('ui.nav_applications')" :subtitle="__('admissions.queue_subtitle')">
    <x-slot:actions>
        <form method="GET" action="{{ route('admin.applications.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
            <label class="form-label mb-0 small" for="status_filter">{{ __('admissions.filter_status') }}</label>
            <select name="status" id="status_filter" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">{{ __('admissions.filter_all_queue') }}</option>
                @foreach($statusOptions as $status)
                    <option value="{{ $status->value }}" @selected(($currentStatus ?? '') === $status->value)>{{ $status->value }}</option>
                @endforeach
            </select>
        </form>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if($applications->isEmpty())
    <x-empty-state :title="__('admissions.queue_empty')" />
@else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('ui.email') }}</th>
                        <th>{{ __('academics.code') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($applications as $application)
                    @php
                        $badgeStatus = match ($application->status) {
                            \App\Enums\ApplicationStatus::Accepted => 'success',
                            \App\Enums\ApplicationStatus::Rejected => 'danger',
                            \App\Enums\ApplicationStatus::Waitlisted => 'waitlist',
                            \App\Enums\ApplicationStatus::UnderReview, \App\Enums\ApplicationStatus::Submitted => 'pending',
                            default => 'neutral',
                        };
                    @endphp
                    <tr>
                        <td>{{ $application->applicant->email }}</td>
                        <td>{{ $application->program->code }}</td>
                        <td>
                            <x-status-badge :status="$badgeStatus" :label="$application->status->value" />
                        </td>
                        <td>
                            <a href="{{ route('admin.applications.show', $application) }}">{{ __('admissions.review') }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    {{ $applications->withQueryString()->links() }}
@endif
@endsection
