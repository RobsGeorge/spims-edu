@extends('layouts.app')
@section('title', __('semesters.page_title'))
@section('content')

<x-page-header :title="__('semesters.page_title')" :subtitle="__('semesters.page_subtitle')">
    <x-slot:actions>
        @can('semesters.manage')
            <button
                type="button"
                class="btn btn-primary"
                @click="$dispatch('open-modal-add-year')"
                aria-haspopup="dialog"
            >
                <x-icon name="add" /> {{ __('semesters.add_year') }}
            </button>
        @endcan
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success mb-4" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger mb-4" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Year selector --}}
@if($years->isNotEmpty())
<nav aria-label="{{ __('semesters.select_year') }}" class="mb-4">
    <x-card variant="quiet" class="p-0">
        <div class="d-flex flex-wrap gap-2 p-3 align-items-center" role="tablist">
            <span class="fw-semibold" style="color: var(--color-text-muted); font-size: var(--text-sm);">
                {{ __('semesters.select_year') }}
            </span>
            @foreach($years as $year)
                @php $isActive = $selectedYear && $selectedYear->id === $year->id; @endphp
                <a
                    href="{{ route('admin.semesters.index', ['year' => $year->id]) }}"
                    role="tab"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}"
                >{{ $year->name }}</a>
            @endforeach
        </div>
    </x-card>
</nav>
@endif

{{-- Timeline for selected year --}}
@if($selectedYear)
    <x-card variant="panel" class="mb-4">
        <x-toolbar>
            <x-slot:start>
                <h2 class="h5 mb-0" style="color: var(--color-title);">{{ $selectedYear->name }}</h2>
            </x-slot:start>
            <x-slot:end>
                @can('semesters.manage')
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        @click="$dispatch('open-modal-add-semester-{{ $selectedYear->id }}')"
                        aria-haspopup="dialog"
                    ><x-icon name="add" size="sm" /> {{ __('semesters.add_semester') }}</button>
                    <a href="{{ route('admin.academic-years.edit', $selectedYear) }}" class="btn btn-sm btn-outline-secondary">
                        <x-icon name="edit" size="sm" /> {{ __('ui.edit') }}
                    </a>
                @endcan
            </x-slot:end>
        </x-toolbar>

        @if($selectedYear->semesters->isEmpty())
            <x-empty-state :title="__('semesters.no_semesters')" icon="bi-calendar2-x" class="py-5" />
        @else
            @php
                $yearStart = $selectedYear->start_date;
                $yearEnd   = $selectedYear->end_date;
                $yearDays  = max(1, $yearStart->diffInDays($yearEnd));
                $todayPct  = $today->between($yearStart, $yearEnd)
                    ? round($yearStart->diffInDays($today) / $yearDays * 100, 4)
                    : null;
            @endphp

            <div
                class="sc-timeline-wrap mt-4"
                style="--today-pct: {{ $todayPct !== null ? $todayPct . '%' : '-200%' }};"
                aria-label="{{ __('semesters.page_title') }}"
            >
                <div class="sc-today-line" aria-hidden="true">
                    <span class="sc-today-label">{{ __('semesters.today_marker') }}</span>
                </div>

                @foreach($selectedYear->semesters as $semester)
                    @php
                        $semStart     = $semester->start_date;
                        $semEnd       = $semester->end_date;
                        $regEnd       = $semester->registration_end;
                        $dropEnd      = $semStart->copy()->addWeeks($semester->add_drop_end_week);
                        $semDays      = max(1, $semStart->diffInDays($semEnd));
                        $semLeft      = round($yearStart->diffInDays($semStart) / $yearDays * 100, 3);
                        $semWidth     = round($semStart->diffInDays($semEnd) / $yearDays * 100, 3);
                        $regWidth     = round($semStart->diffInDays(min($regEnd, $semEnd)) / $semDays * 100, 3);
                        $dropWidth    = round($semStart->diffInDays(min($dropEnd, $semEnd)) / $semDays * 100, 3);
                        $nextStates   = App\Enums\SemesterStatus::legalNextStates($semester->status);
                        $statusStr    = $semester->status->name; // CSS data attr — use name not value to avoid ->value pattern
                    @endphp

                    <div class="sc-semester mb-4" data-status="{{ $statusStr }}">
                        <div class="sc-semester-header d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="fw-semibold" style="font-size: var(--text-sm);">{{ $semester->name }}</span>
                            <x-badge :value="$semester->status" />

                            @can('semesters.manage')
                                @foreach($nextStates as $next)
                                    @php
                                        $nextStatusVal  = $next->name;
                                        $nextStatusKey  = strtolower($next->value);
                                        $nextStatusForm = $next->value;
                                    @endphp
                                    <form
                                        method="POST"
                                        action="{{ route('admin.semesters.transition', $semester) }}"
                                        class="d-inline"
                                    >
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $nextStatusForm }}">
                                        <button type="submit" class="btn btn-xs btn-outline-primary">
                                            {{ __('semesters.advance_to', ['status' => __('semesters.status_' . $nextStatusKey)]) }}
                                        </button>
                                    </form>
                                @endforeach
                                <a href="{{ route('admin.semesters.edit', $semester) }}" class="btn btn-xs btn-outline-secondary ms-auto">
                                    <x-icon name="edit" size="sm" /> {{ __('ui.edit') }}
                                </a>
                            @endcan
                        </div>

                        <div class="sc-bar-track" role="img" aria-label="{{ $semester->name }}">
                            <div class="sc-bar" style="--sem-left: {{ $semLeft }}%; --sem-width: {{ $semWidth }}%;">
                                <div class="sc-region sc-region-teach" style="--region-width: 100%;" title="{{ __('semesters.teaching_period') }}">
                                    <span class="sc-region-label">{{ __('semesters.teaching_period') }}</span>
                                </div>
                                <div class="sc-region sc-region-drop" style="--region-width: {{ $dropWidth }}%;" title="{{ __('semesters.add_drop_window') }}">
                                    <span class="sc-region-label">{{ __('semesters.add_drop_window') }}</span>
                                </div>
                                <div class="sc-region sc-region-reg" style="--region-width: {{ $regWidth }}%;" title="{{ __('semesters.registration_window') }}">
                                    <span class="sc-region-label">{{ __('semesters.registration_window') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="sc-date-range mt-1" style="font-size: var(--text-xs); color: var(--color-text-muted);">
                            {{ $semStart->format('M j, Y') }} &mdash; {{ $semEnd->format('M j, Y') }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
@else
    <x-empty-state :title="__('semesters.no_years')" icon="bi-calendar3" class="py-6" />
@endif

{{-- Modals --}}
@can('semesters.manage')

<x-modal id="add-year" :title="__('semesters.add_year')" size="md">
    <form method="POST" action="{{ route('admin.academic-years.store') }}" id="add-year-form">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-field :label="__('semesters.year_name')" name="name" required>
                    <input type="text" name="name" class="form-control" placeholder="2027/2028" required>
                </x-field>
            </div>
            <div class="col-12 col-sm-6">
                <x-field :label="__('offerings.start_date')" name="start_date" required>
                    <input type="date" name="start_date" class="form-control" required>
                </x-field>
            </div>
            <div class="col-12 col-sm-6">
                <x-field :label="__('offerings.end_date')" name="end_date" required>
                    <input type="date" name="end_date" class="form-control" required>
                </x-field>
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="add-year-form" class="btn btn-primary">{{ __('ui.save_changes') }}</button>
        <button type="button" class="btn btn-outline-secondary" @click="open = false">{{ __('ui.cancel') }}</button>
    </x-slot:footer>
</x-modal>

@if($selectedYear)
<x-modal id="add-semester-{{ $selectedYear->id }}" :title="__('semesters.add_semester')" size="lg">
    <form method="POST" action="{{ route('admin.semesters.store', $selectedYear) }}" id="add-semester-form">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <x-field :label="__('academics.name')" name="name" required>
                    <input type="text" name="name" class="form-control" required>
                </x-field>
            </div>
            <div class="col-12 col-md-4">
                <x-field :label="__('offerings.start_date')" name="start_date" required>
                    <input type="date" name="start_date" class="form-control" required>
                </x-field>
            </div>
            <div class="col-12 col-md-4">
                <x-field :label="__('offerings.end_date')" name="end_date" required>
                    <input type="date" name="end_date" class="form-control" required>
                </x-field>
            </div>
            <div class="col-12 col-md-4">
                <x-field :label="__('offerings.registration_start')" name="registration_start" required>
                    <input type="date" name="registration_start" class="form-control" required>
                </x-field>
            </div>
            <div class="col-12 col-md-4">
                <x-field :label="__('offerings.registration_end')" name="registration_end" required>
                    <input type="date" name="registration_end" class="form-control" required>
                </x-field>
            </div>
            <div class="col-6 col-md-2">
                <x-field :label="__('offerings.add_drop_week')" name="add_drop_end_week" required>
                    <input type="number" name="add_drop_end_week" class="form-control" value="2" min="1" required>
                </x-field>
            </div>
            <div class="col-6 col-md-2">
                <x-field :label="__('offerings.withdrawal_week')" name="last_withdrawal_week" required>
                    <input type="number" name="last_withdrawal_week" class="form-control" value="8" min="1" required>
                </x-field>
            </div>
            <div class="col-12 col-md-4">
                <x-field :label="__('offerings.withdrawal_refund')" name="withdrawal_refund_percent">
                    <input type="number" step="0.01" name="withdrawal_refund_percent" class="form-control" value="50" min="0" max="100">
                </x-field>
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="add-semester-form" class="btn btn-primary">{{ __('offerings.add_semester') }}</button>
        <button type="button" class="btn btn-outline-secondary" @click="open = false">{{ __('ui.cancel') }}</button>
    </x-slot:footer>
</x-modal>
@endif

@endcan

<style>
/* Semester Calendar ------------------------------------------------------ */
.sc-timeline-wrap {
    position: relative;
    padding-block: var(--space-2);
    overflow: hidden;
}
.sc-today-line {
    position: absolute;
    inset-block: 0;
    inset-inline-start: var(--today-pct, -200%);
    width: 2px;
    background: var(--color-danger);
    pointer-events: none;
    z-index: 2;
}
.sc-today-label {
    position: absolute;
    inset-block-start: 0;
    inset-inline-start: var(--space-2);
    font-size: var(--text-xs);
    color: var(--color-danger);
    white-space: nowrap;
    font-weight: 600;
}
.sc-bar-track {
    position: relative;
    height: 2.5rem;
    background: var(--color-bg-3);
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-hairline);
    overflow: hidden;
}
.sc-bar {
    position: absolute;
    inset-block: 0;
    inset-inline-start: var(--sem-left, 0%);
    width: var(--sem-width, 100%);
}
.sc-region {
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    width: var(--region-width, 100%);
    display: flex;
    align-items: center;
    padding-inline: var(--space-2);
    overflow: hidden;
}
.sc-region-teach {
    background: rgba(93, 3, 38, 0.10);
    border-inline-start: 3px solid var(--color-primary);
}
.sc-region-drop {
    background: rgba(245, 158, 11, 0.15);
    border-inline-start: 3px solid var(--color-warning);
}
.sc-region-reg {
    background: rgba(16, 185, 129, 0.20);
    border-inline-start: 3px solid var(--color-success);
    z-index: 1;
}
body.theme-dark .sc-region-teach { background: rgba(255, 177, 192, 0.10); }
body.theme-dark .sc-region-drop  { background: rgba(245, 158, 11, 0.12); }
body.theme-dark .sc-region-reg   { background: rgba(16, 185, 129, 0.15); }
.sc-region-label {
    font-size: var(--text-xs);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--color-text);
    opacity: 0.85;
}
.btn-xs {
    padding: 0.15rem 0.5rem;
    font-size: var(--text-xs);
    border-radius: var(--radius-sm);
    min-height: 1.75rem;
}
@media (prefers-reduced-motion: reduce) {
    .sc-today-line, .sc-bar, .sc-region { transition: none; }
}
</style>

@endsection
