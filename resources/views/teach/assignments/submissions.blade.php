@extends('layouts.app')
@section('title', __('grading.roster_title'))
@section('content')

<x-page-header
    :title="__('grading.roster_title')"
    :subtitle="$assignment->instructions"
    :eyebrow="$offering->course->code"
>
    <x-slot:actions>
        @if($nextUngraded)
            <a
                href="{{ route('teach.assignments.submissions.next-ungraded', [$offering, $assignment]) }}"
                class="btn btn-primary"
            >
                <x-icon name="grade" />
                {{ __('grading.grade_next') }}
            </a>
        @else
            <span class="btn btn-outline-secondary disabled" aria-disabled="true">
                {{ __('grading.nothing_to_grade') }}
            </span>
        @endif
        <a
            href="{{ route('teach.assignments.index', $offering) }}"
            class="btn btn-outline-secondary btn-sm"
        >{{ __('teach.back') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success mt-3" role="status">{{ session('status') }}</div>
@endif

@if(session('nothing_to_grade'))
    <div class="alert alert-info mt-3" role="status">
        <x-icon name="success" />
        {{ __('grading.nothing_to_grade_msg') }}
    </div>
@endif

{{-- Filters --}}
<x-card variant="quiet" class="mt-3 mb-4">
    <form method="GET"
          action="{{ route('teach.assignments.submissions.index', [$offering, $assignment]) }}"
          class="row g-2 align-items-end">

        <div class="col-12 col-sm-4">
            <label class="form-label small fw-semibold" for="filter-status">
                {{ __('grading.filter_label') }}
            </label>
            <select id="filter-status" name="status" class="form-select form-select-sm">
                <option value="all"          @selected($statusFilter === 'all')>{{ __('grading.status_all') }}</option>
                <option value="submitted"    @selected($statusFilter === 'submitted')>{{ __('grading.status_submitted') }}</option>
                <option value="graded"       @selected($statusFilter === 'graded')>{{ __('grading.status_graded') }}</option>
                <option value="not_submitted" @selected($statusFilter === 'not_submitted')>{{ __('grading.status_not_submitted') }}</option>
            </select>
        </div>

        <div class="col-12 col-sm-3">
            <label class="form-label small fw-semibold" for="filter-from">
                {{ __('grading.filter_date_from') }}
            </label>
            <input
                id="filter-from"
                type="date"
                name="from"
                value="{{ $from }}"
                class="form-control form-control-sm"
            >
        </div>

        <div class="col-12 col-sm-3">
            <label class="form-label small fw-semibold" for="filter-to">
                {{ __('grading.filter_date_to') }}
            </label>
            <input
                id="filter-to"
                type="date"
                name="to"
                value="{{ $to }}"
                class="form-control form-control-sm"
            >
        </div>

        <div class="col-12 col-sm-2">
            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                {{ __('grading.filter_label') }}
            </button>
        </div>
    </form>
</x-card>

{{-- Roster table --}}
@if($roster->isEmpty())
    <x-empty-state
        :title="__('grading.no_submissions')"
        icon="bi-inbox"
    />
@else
    <div class="spims-table-wrap">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th scope="col">{{ __('grading.student_col') }}</th>
                    <th scope="col">{{ __('ui.status') }}</th>
                    <th scope="col" class="text-end" style="font-variant-numeric: tabular-nums">{{ __('grading.version_col') }}</th>
                    <th scope="col" class="text-end" style="font-variant-numeric: tabular-nums">{{ __('grading.score_col') }}</th>
                    <th scope="col">{{ __('grading.submitted_at_col') }}</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($roster as $row)
                    @php
                        if ($row->submission_id === null) {
                            $statusEnum = \App\Enums\SubmissionStatus::NotSubmitted;
                        } elseif ($row->final_score !== null) {
                            $statusEnum = \App\Enums\SubmissionStatus::Graded;
                        } else {
                            $statusEnum = \App\Enums\SubmissionStatus::Submitted;
                        }
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $row->first_name }} {{ $row->last_name }}</strong>
                            <div class="spims-text-dim" style="font-size: var(--text-sm)">{{ $row->email }}</div>
                        </td>
                        <td><x-badge :value="$statusEnum" /></td>
                        <td class="text-end" style="font-variant-numeric: tabular-nums">
                            {{ $row->attempt_no ?? '—' }}
                        </td>
                        <td class="text-end" style="font-variant-numeric: tabular-nums">
                            {{ $row->final_score !== null ? number_format((float)$row->final_score, 1) : '—' }}
                        </td>
                        <td>
                            @if($row->submitted_at)
                                {{ \Carbon\Carbon::parse($row->submitted_at)->format('Y-m-d H:i') }}
                                @if($row->is_late)
                                    <span class="badge spims-badge spims-badge--warning ms-1">{{ __('grading.is_late_label') }}</span>
                                @endif
                            @else
                                <span class="spims-text-dim">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row->submission_id)
                                <a
                                    href="{{ route('teach.assignments.submissions.show', [$offering, $assignment, $row->submission_id]) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <x-icon name="grade" size="sm" />
                                    {{ __('grading.grade_action') }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $roster->links() }}
    </div>
@endif

@endsection
