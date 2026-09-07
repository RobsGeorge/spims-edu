@extends('layouts.app')

@section('title', __('school_reports.reports.'.$report.'.title').' · '.__('school_reports.page_title'))

@section('content')
<div class="sa-reports animate-in">
    <nav class="mb-3 small" aria-label="{{ __('school_reports.reports.'.$report.'.title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('school_reports.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <a href="{{ route('superadmin.reports', $rangeQuery) }}" class="text-decoration-none text-muted-theme">{{ __('school_reports.page_title') }}</a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('school_reports.reports.'.$report.'.title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('school_reports.reports.'.$report.'.title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('school_reports.reports.'.$report.'.desc') }}</p>
        <p class="small text-muted-theme mb-2">{{ __('school_reports.reports.'.$report.'.hint') }}</p>
        <p class="mb-0">
            @php
                $kind = collect($catalog)->firstWhere('slug', $report)['kind'] ?? 'snapshot';
            @endphp
            <span class="sa-report-badge sa-report-badge-{{ $kind }}">{{ __('school_reports.'.$kind.'_badge') }}</span>
        </p>
    </header>

    @foreach($payload['notes'] as $note)
        <aside class="sa-callout sa-callout-info mb-3" role="note">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <div>
                <p class="mb-0">{{ $note }}</p>
            </div>
        </aside>
    @endforeach

    @if($report === 'finance')
        <aside class="sa-callout sa-callout-danger mb-4" role="note">
            <i class="bi bi-cash-stack" aria-hidden="true"></i>
            <div>
                <strong>{{ __('school_reports.money_title') }}</strong>
                <p class="mb-0">{{ __('school_reports.finance_money_help') }}</p>
            </div>
        </aside>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-primary btn-sm" href="{{ route('superadmin.reports.csv', array_merge(['report' => $report], $rangeQuery)) }}">
            {{ __('school_reports.download_csv') }}
        </a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.reports', $rangeQuery) }}">
            {{ __('school_reports.back_hub') }}
        </a>
    </div>
    <p class="form-text mb-4">{{ __('school_reports.csv_help') }}</p>

    @include('partials.reports-range-form', [
        'rangeAction' => route('superadmin.reports.show', $report),
        'resetUrl' => route('superadmin.reports.show', $report),
    ])

    <section class="mb-4" aria-labelledby="sa-report-summary">
        <h2 class="h5 page-title mb-1" id="sa-report-summary">{{ __('school_reports.summary_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('school_reports.summary_help') }}</p>
        <div class="row g-3">
            @foreach($payload['summary'] as $tile)
                <div class="col-12 col-md-6 col-xl-4">
                    <article class="app-card p-3 h-100 sa-report-summary">
                        <h3 class="h6 page-title mb-1">{{ $tile['label'] }}</h3>
                        <p class="display-6 fw-bold page-title mb-1">{{ $tile['value'] }}</p>
                        @if(array_key_exists('minor', $tile))
                            <p class="mb-1">
                                <code>{{ $tile['minor'] }}</code>
                                <span class="form-text">{{ __('school_reports.col_minor') }}</span>
                            </p>
                        @endif
                        <p class="form-text mb-0">{{ $tile['help'] }}</p>
                    </article>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-4" aria-labelledby="sa-report-table">
        <h2 class="h5 page-title mb-1" id="sa-report-table">{{ __('school_reports.table_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('school_reports.table_help') }}</p>
        @if($payload['rows'] === [])
            <x-empty-state :title="__('school_reports.empty_rows')" icon="bi-table" />
            <p class="form-text">{{ __('school_reports.empty_rows_help') }}</p>
        @else
            <div class="table-responsive app-card p-0">
                <table class="table align-middle mb-0 sa-report-table">
                    <thead>
                        <tr>
                            @foreach($payload['headers'] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payload['rows'] as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if($payload['deep_links'] !== [])
        <section class="mb-4" aria-labelledby="sa-report-links">
            <h2 class="h6 page-title mb-1" id="sa-report-links">{{ __('school_reports.deep_link') }}</h2>
            <p class="form-text mb-3">{{ __('school_reports.deep_link_help') }}</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach($payload['deep_links'] as $link)
                    <a class="btn btn-outline-primary btn-sm" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
