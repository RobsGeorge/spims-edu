@extends('layouts.app')

@section('title', __('school_reports.page_title'))

@section('content')
@php
    $must = ['census', 'finance', 'audit'];
    $mustReports = collect($reports)->whereIn('slug', $must)->values();
    $readyReports = collect($reports)->reject(fn ($item) => in_array($item['slug'], $must, true))->values();
@endphp
<div class="sa-reports animate-in">
    <nav class="mb-3 small" aria-label="{{ __('school_reports.page_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('school_reports.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('school_reports.page_title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('school_reports.page_title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('school_reports.page_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('school_reports.page_help') }}</p>
    </header>
    @include('partials.status-entrance-banner')

    <aside class="sa-callout sa-callout-danger mb-3" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('school_reports.danger_title') }}</strong>
            <p class="mb-0">{{ __('school_reports.hub_callout') }}</p>
        </div>
    </aside>
    <aside class="sa-callout sa-callout-info mb-4" role="note">
        <i class="bi bi-cash-stack" aria-hidden="true"></i>
        <div>
            <strong>{{ __('school_reports.money_title') }}</strong>
            <p class="mb-0">{{ __('school_reports.hub_callout_money') }}</p>
        </div>
    </aside>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @if(\Illuminate\Support\Facades\Route::has('admin.reports.index'))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.reports.index') }}">{{ __('school_reports.open_registrar') }}</a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('admin.finance.reports'))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.finance.reports') }}">{{ __('school_reports.open_finance_ops') }}</a>
        @endif
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.audit.index') }}">{{ __('school_reports.open_audit') }}</a>
    </div>
    <p class="small text-muted-theme mb-4">{{ __('school_reports.deep_link_help') }}</p>

    @include('partials.reports-range-form')

    <section class="mb-4" aria-labelledby="sa-reports-must">
        <h2 class="h5 page-title mb-1" id="sa-reports-must">{{ __('school_reports.must_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('school_reports.must_help') }}</p>
        <div class="row g-3">
            @foreach($mustReports as $item)
                @include('partials.reports-hub-card', ['item' => $item, 'rangeQuery' => $rangeQuery])
            @endforeach
        </div>
    </section>

    <section class="mb-4" aria-labelledby="sa-reports-ready">
        <h2 class="h5 page-title mb-1" id="sa-reports-ready">{{ __('school_reports.when_ready_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('school_reports.when_ready_help') }}</p>
        <div class="row g-3">
            @foreach($readyReports as $item)
                @include('partials.reports-hub-card', ['item' => $item, 'rangeQuery' => $rangeQuery])
            @endforeach
        </div>
    </section>
</div>
@endsection
