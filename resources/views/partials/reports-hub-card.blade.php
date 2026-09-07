@php
    $slug = $item['slug'];
    $available = !empty($item['available']);
@endphp
<div class="col-12 col-lg-6">
    <article class="app-card p-3 sa-report-card h-100 {{ $available ? 'is-ready' : 'is-gated' }}" data-report="{{ $slug }}">
        <div class="d-flex align-items-start gap-3">
            <span class="sa-report-icon" aria-hidden="true">
                <i class="bi {{ $item['icon'] }}"></i>
            </span>
            <div class="min-w-0 flex-grow-1">
                <h3 class="h6 page-title mb-1">{{ __('school_reports.reports.'.$slug.'.title') }}</h3>
                <p class="small text-muted-theme mb-2">{{ __('school_reports.reports.'.$slug.'.desc') }}</p>
                <p class="mb-2">
                    <span class="sa-report-badge sa-report-badge-{{ $item['kind'] }}">
                        {{ __('school_reports.'.$item['kind'].'_badge') }}
                    </span>
                </p>
                <p class="form-text mb-3">{{ __('school_reports.reports.'.$slug.'.hint') }}</p>
                @if($available)
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary btn-sm" href="{{ route('superadmin.reports.show', array_merge(['report' => $slug], $rangeQuery)) }}">
                            {{ __('school_reports.open_report') }}
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('superadmin.reports.csv', array_merge(['report' => $slug], $rangeQuery)) }}">
                            {{ __('school_reports.download_csv') }}
                        </a>
                    </div>
                    <p class="form-text mb-0 mt-2">{{ __('school_reports.open_report_help') }} {{ __('school_reports.download_csv_help') }}</p>
                @else
                    <p class="mb-1"><strong>{{ __('school_reports.unavailable') }}</strong></p>
                    <p class="form-text mb-0">{{ __('school_reports.unavailable_help') }}
                        @if(!empty($item['table']))
                            · <code>{{ $item['table'] }}</code>
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </article>
</div>
