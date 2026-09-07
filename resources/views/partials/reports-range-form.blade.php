@php
    $rangeAction = $rangeAction ?? route('superadmin.reports');
    $resetUrl = $resetUrl ?? route('superadmin.reports');
    $source = $range['source'] ?? 'trailing_90';
    $sourceLabel = match ($source) {
        'custom' => __('school_reports.range_source_custom'),
        'semester' => __('school_reports.range_source_semester'),
        default => __('school_reports.range_source_trailing'),
    };
@endphp
<section class="sa-report-range app-card p-3 mb-4" aria-labelledby="sa-report-range-title">
    <h2 class="h6 page-title mb-1" id="sa-report-range-title">{{ __('school_reports.range_legend') }}</h2>
    <p class="small text-muted-theme mb-3">{{ __('school_reports.range_help') }}</p>
    <p class="small mb-3">
        <strong>{{ __('school_reports.range_applied') }}:</strong>
        {{ $range['from']->toDateString() }}
        →
        {{ $range['to']->toDateString() }}
        <span class="text-muted-theme">· {{ $sourceLabel }}</span>
        @if(!empty($range['label']) && $source === 'semester')
            <span class="text-muted-theme">· {{ $range['label'] }}</span>
        @endif
    </p>
    <form method="GET" action="{{ $rangeAction }}" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="sa-report-from">{{ __('school_reports.range_from') }}</label>
            <input id="sa-report-from" type="date" name="from" class="form-control @error('from') is-invalid @enderror"
                   value="{{ old('from', $range['from']->toDateString()) }}" required>
            <p class="form-text mb-0">{{ __('school_reports.range_from_help') }}</p>
            @error('from')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sa-report-to">{{ __('school_reports.range_to') }}</label>
            <input id="sa-report-to" type="date" name="to" class="form-control @error('to') is-invalid @enderror"
                   value="{{ old('to', $range['to']->toDateString()) }}" required>
            <p class="form-text mb-0">{{ __('school_reports.range_to_help') }}</p>
            @error('to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 d-flex flex-wrap gap-2">
            <button class="btn btn-primary">{{ __('school_reports.range_apply') }}</button>
            <a class="btn btn-outline-secondary" href="{{ $resetUrl }}">{{ __('school_reports.range_reset') }}</a>
            <p class="form-text mb-0 w-100">{{ __('school_reports.range_reset_help') }}</p>
        </div>
    </form>
</section>
