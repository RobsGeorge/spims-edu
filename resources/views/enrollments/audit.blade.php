@extends('layouts.app')
@section('title', __('enrollment.degree_audit'))
@section('content')
<x-page-header
    :title="__('enrollment.degree_audit').' · '.$audit['program']"
    :subtitle="__('learning.overall_progress').': '.$audit['overall_percent'].'%'"
    :eyebrow="!empty($audit['what_if']) ? __('advising.what_if_active') : null"
>
    <x-slot:actions>
        <a class="btn btn-outline-secondary" href="{{ route('enrollments.index') }}">{{ __('ui.nav_enrollments') }}</a>
    </x-slot:actions>
</x-page-header>

@if(!empty($audit['what_if']))
    <div class="alert alert-info border-0 shadow-sm mb-4">{{ __('advising.what_if_active') }} · {{ __('advising.what_if_help') }}</div>
@endif

<div class="progress mb-4" role="progressbar" aria-valuenow="{{ (int) $audit['overall_percent'] }}" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar" style="width: {{ $audit['overall_percent'] }}%; background: var(--color-primary);"></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted-theme small">{{ __('enrollment.required_progress') }}</div>
                <div class="h4 mb-0">{{ $audit['required_met'] }} / {{ $audit['required_total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted-theme small">{{ __('enrollment.elective_progress') }}</div>
                <div class="h4 mb-0">{{ $audit['elective_credits_met'] }} / {{ $audit['elective_credits_required'] }}</div>
                <div class="small text-muted-theme mt-1">
                    {{ __('advising.elective_remaining') }}:
                    {{ max(0, $audit['elective_credits_required'] - $audit['elective_credits_met']) }}
                </div>
            </div>
        </div>
    </div>
</div>

<section class="card border-0 shadow-sm mb-3" id="audit-met">
    <div class="card-body">
        <h2 class="h5 spims-title">{{ __('learning.audit_met') }}</h2>
        @forelse($audit['met'] as $item)
            <div class="py-2 border-bottom border-opacity-25 d-flex justify-content-between gap-2" data-audit-met-code="{{ $item['code'] }}">
                <div>{{ $item['code'] }} · {{ $item['title'] }} <span class="small text-muted-theme">({{ $item['requirement'] }})</span></div>
                <div>{{ $item['letter'] ?? '—' }}@if($item['percent'] !== null) · {{ number_format($item['percent'], 1) }}%@endif</div>
            </div>
        @empty
            <x-empty-state :title="__('enrollment.audit_met_empty')" icon="bi-journal-check" />
        @endforelse
    </div>
</section>

<section class="card border-0 shadow-sm mb-3" id="audit-remaining">
    <div class="card-body">
        <h2 class="h5 spims-title">{{ __('learning.audit_remaining') }}</h2>
        @forelse($audit['remaining'] as $item)
            <div class="py-2 border-bottom border-opacity-25" data-audit-remaining-code="{{ $item['code'] }}">
                {{ $item['code'] }} · {{ $item['title'] }}
                <span class="small text-muted-theme">({{ $item['requirement'] }})</span>
            </div>
        @empty
            <x-empty-state :title="__('enrollment.audit_remaining_empty')" icon="bi-check2-circle" />
        @endforelse
    </div>
</section>

@if(isset($remainingCourses) && $remainingCourses->isNotEmpty())
<section class="card border-0 shadow-sm" id="audit-what-if">
    <div class="card-body">
        <h2 class="h5 spims-title">{{ __('advising.what_if') }}</h2>
        <p class="text-muted-theme">{{ __('advising.what_if_help') }}</p>
        <form method="GET" action="{{ route('enrollments.audit', $studentProgram) }}" class="row g-2">
            @foreach($remainingCourses as $pc)
                <div class="col-12">
                    <label class="form-check" data-what-if-course="{{ $pc->course->code }}">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            name="hypothetical_course_ids[]"
                            value="{{ $pc->course_id }}"
                            @checked(in_array($pc->course_id, $selectedHypothetical ?? [], true))
                        >
                        <span class="form-check-label">
                            {{ $pc->course->code }} · {{ $pc->course->title }}
                            <span class="small text-muted-theme">({{ $pc->requirement->value }} · {{ $pc->course->credit_hours }})</span>
                        </span>
                    </label>
                </div>
            @endforeach
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary">{{ __('advising.run_what_if') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('enrollments.audit', $studentProgram) }}">{{ __('advising.reset_what_if') }}</a>
            </div>
        </form>
    </div>
</section>
@endif
@endsection
