@extends('layouts.app')

@section('title', __('ui.nav_applications'))

@section('content')
<div class="admin-console animate-in">
    <x-page-header :title="__('ui.nav_applications')" :subtitle="__('admissions.queue_subtitle')">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.applications.index') }}" class="spims-filter-bar academic-form d-flex flex-wrap gap-2 align-items-center py-2 px-3 mb-0">
                <label class="form-label mb-0 small" for="status_filter">{{ __('admissions.filter_status') }}</label>
                <select name="status" id="status_filter" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="">{{ __('admissions.filter_all_queue') }}</option>
                    @foreach($statusOptions as $status)
                        @php $statusVal = $status->value; @endphp
                        <option value="{{ $statusVal }}" @selected(($currentStatus ?? '') === $statusVal)>{{ $statusVal }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
    </x-page-header>

    @if(session('status'))
        <div class="alert alert-success academic-alert">{{ session('status') }}</div>
    @endif

    @if($applications->isEmpty())
        <x-empty-state :title="__('admissions.queue_empty')" />
    @else
        <div class="spims-data-panel">
            <div class="spims-table-wrap spims-table-wrap--cards">
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
                        <tr>
                            <td data-label="{{ __('ui.email') }}">{{ $application->applicant->email }}</td>
                            <td data-label="{{ __('academics.code') }}">{{ $application->program->code }}</td>
                            <td data-label="{{ __('ui.status') }}">
                                <x-status-badge :status="$application->status->badgeTone()" :label="$application->status->value" />
                            </td>
                            <td data-label="">
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
</div>
@endsection
