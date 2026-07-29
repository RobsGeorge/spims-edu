@extends('layouts.app')
@section('title', __('enrollment.degree_audit'))
@section('content')
<div class="animate-in">
    <h1 class="spims-title mb-2">{{ __('enrollment.degree_audit') }} · {{ $audit['program'] }}</h1>
    <p class="text-muted-theme mb-4">{{ __('learning.overall_progress') }}: {{ $audit['overall_percent'] }}%</p>

    <div class="progress mb-4" role="progressbar" aria-valuenow="{{ (int) $audit['overall_percent'] }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" style="width: {{ $audit['overall_percent'] }}%; background: var(--color-primary);"></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="app-card p-3">
                <div class="text-muted-theme small">{{ __('enrollment.required_progress') }}</div>
                <div class="h4 mb-0">{{ $audit['required_met'] }} / {{ $audit['required_total'] }}</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="app-card p-3">
                <div class="text-muted-theme small">{{ __('enrollment.elective_progress') }}</div>
                <div class="h4 mb-0">{{ $audit['elective_credits_met'] }} / {{ $audit['elective_credits_required'] }}</div>
            </div>
        </div>
    </div>

    <section class="app-card p-3 mb-3">
        <h2 class="h5 spims-title">{{ __('learning.audit_met') }}</h2>
        @forelse($audit['met'] as $item)
            <div class="py-2 border-bottom border-opacity-25 d-flex justify-content-between gap-2">
                <div>{{ $item['code'] }} · {{ $item['title'] }} <span class="small text-muted-theme">({{ $item['requirement'] }})</span></div>
                <div>{{ $item['letter'] ?? '—' }}@if($item['percent'] !== null) · {{ number_format($item['percent'], 1) }}%@endif</div>
            </div>
        @empty
            <p class="text-muted-theme mb-0">—</p>
        @endforelse
    </section>

    <section class="app-card p-3">
        <h2 class="h5 spims-title">{{ __('learning.audit_remaining') }}</h2>
        @forelse($audit['remaining'] as $item)
            <div class="py-2 border-bottom border-opacity-25">
                {{ $item['code'] }} · {{ $item['title'] }}
                <span class="small text-muted-theme">({{ $item['requirement'] }})</span>
            </div>
        @empty
            <p class="text-muted-theme mb-0">—</p>
        @endforelse
    </section>
</div>
@endsection
