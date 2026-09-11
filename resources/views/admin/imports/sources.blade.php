@extends('layouts.app')

@section('title', __('import.sources_title'))

@section('content')
<nav class="mb-3 small" aria-label="breadcrumb">
    <a href="{{ route('admin.imports.index') }}" class="text-decoration-none spims-text-dim">{{ __('import.title') }}</a>
    <span class="spims-text-dim mx-1">/</span>
    <span class="spims-text-dim">{{ __('import.sources_title') }}</span>
</nav>

<x-page-header :title="__('import.sources_title')" :subtitle="__('import.sources_lead')" />

@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

@forelse($sources as $source)
    <x-card variant="panel" class="mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 page-title mb-0">{{ $source->name }} <span class="small spims-text-dim">({{ $source->code }})</span></h2>
            <span class="spims-status-badge spims-status-{{ $source->active ? 'success' : 'neutral' }}">
                {{ $source->active ? __('import.source_active') : __('import.source_inactive') }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.imports.sources.update', $source) }}" class="row g-3 academic-form mb-4">
            @csrf
            <div class="col-md-3">
                <label class="form-label" for="name-{{ $source->id }}">{{ __('import.source_name') }}</label>
                <input id="name-{{ $source->id }}" name="name" class="form-control" value="{{ $source->name }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="kind-{{ $source->id }}">{{ __('import.source_kind') }}</label>
                <select id="kind-{{ $source->id }}" name="kind" class="form-select">
                    <option value="SIS" @selected($source->kind->value === 'SIS')>{{ __('import.source_kind_SIS') }}</option>
                    <option value="LMS" @selected($source->kind->value === 'LMS')>{{ __('import.source_kind_LMS') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="precedence-{{ $source->id }}">{{ __('import.source_precedence') }}</label>
                <input id="precedence-{{ $source->id }}" name="precedence" type="number" min="1" max="99" class="form-control" value="{{ $source->precedence }}" required>
                <p class="form-text mb-0">{{ __('import.source_precedence_help') }}</p>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="gpa-{{ $source->id }}">{{ __('import.source_gpa_scale') }}</label>
                <input id="gpa-{{ $source->id }}" name="gpa_scale_max" type="number" step="0.01" min="1" max="20" class="form-control" value="{{ $source->gpa_scale_max }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="currency-{{ $source->id }}">{{ __('import.source_currency') }}</label>
                <select id="currency-{{ $source->id }}" name="default_currency" class="form-select">
                    <option value="EGP" @selected($source->default_currency === 'EGP')>EGP</option>
                    <option value="USD" @selected($source->default_currency === 'USD')>USD</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="tz-{{ $source->id }}">{{ __('import.source_timezone') }}</label>
                <input id="tz-{{ $source->id }}" name="timezone" class="form-control" value="{{ $source->timezone }}" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="active-{{ $source->id }}" name="active" value="1" @checked($source->active)>
                    <label class="form-check-label" for="active-{{ $source->id }}">{{ __('import.source_active') }}</label>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary">{{ __('import.save_source') }}</button>
            </div>
        </form>

        <h3 class="h6 page-title mb-1">{{ __('import.grade_mappings_title') }}</h3>
        <p class="small spims-text-dim mb-3">{{ __('import.grade_mappings_help') }}</p>

        <div class="table-responsive spims-table-wrap mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('import.col_legacy_letter') }}</th>
                        <th>{{ __('import.col_min_percent') }}</th>
                        <th>{{ __('import.col_max_percent') }}</th>
                        <th>{{ __('import.col_spims_letter') }}</th>
                        <th>{{ __('import.col_gpa_points') }}</th>
                        <th>{{ __('import.col_is_passing') }}</th>
                        <th>{{ __('import.col_counts_gpa') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($source->gradeMappings as $grade)
                    <tr>
                        <td><input form="grade-edit-{{ $grade->id }}" name="legacy_letter" class="form-control form-control-sm" value="{{ $grade->legacy_letter }}" style="width: 6rem"></td>
                        <td><input form="grade-edit-{{ $grade->id }}" name="min_percent" type="number" step="0.1" class="form-control form-control-sm" value="{{ $grade->min_percent }}" style="width: 5rem"></td>
                        <td><input form="grade-edit-{{ $grade->id }}" name="max_percent" type="number" step="0.1" class="form-control form-control-sm" value="{{ $grade->max_percent }}" style="width: 5rem"></td>
                        <td><input form="grade-edit-{{ $grade->id }}" name="spims_letter" class="form-control form-control-sm" value="{{ $grade->spims_letter }}" style="width: 4rem" required></td>
                        <td><input form="grade-edit-{{ $grade->id }}" name="gpa_points" type="number" step="0.01" class="form-control form-control-sm" value="{{ $grade->gpa_points }}" style="width: 5rem" required></td>
                        <td class="text-center"><input form="grade-edit-{{ $grade->id }}" type="checkbox" name="is_passing" value="1" @checked($grade->is_passing)></td>
                        <td class="text-center"><input form="grade-edit-{{ $grade->id }}" type="checkbox" name="counts_toward_gpa" value="1" @checked($grade->counts_toward_gpa) title="{{ __('import.counts_gpa_help') }}"></td>
                        <td class="text-nowrap">
                            <button form="grade-edit-{{ $grade->id }}" class="btn btn-sm btn-outline-primary">{{ __('import.save') }}</button>
                            <button form="grade-delete-{{ $grade->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ __('import.delete') }}?')">{{ __('import.delete') }}</button>
                        </td>
                    </tr>
                    <form id="grade-edit-{{ $grade->id }}" method="POST" action="{{ route('admin.imports.sources.grades.update', [$source, $grade]) }}">@csrf</form>
                    <form id="grade-delete-{{ $grade->id }}" method="POST" action="{{ route('admin.imports.sources.grades.destroy', [$source, $grade]) }}">@csrf @method('DELETE')</form>
                @empty
                    <tr><td colspan="8" class="small spims-text-dim">{{ __('import.no_grade_mappings') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="small spims-text-dim mb-3">{{ __('import.counts_gpa_help') }}</p>

        <form method="POST" action="{{ route('admin.imports.sources.grades.store', $source) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-auto">
                <label class="form-label small mb-0" for="new-letter-{{ $source->id }}">{{ __('import.col_legacy_letter') }}</label>
                <input id="new-letter-{{ $source->id }}" name="legacy_letter" class="form-control form-control-sm" style="width: 6rem">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0" for="new-min-{{ $source->id }}">{{ __('import.col_min_percent') }}</label>
                <input id="new-min-{{ $source->id }}" name="min_percent" type="number" step="0.1" class="form-control form-control-sm" style="width: 5rem">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0" for="new-max-{{ $source->id }}">{{ __('import.col_max_percent') }}</label>
                <input id="new-max-{{ $source->id }}" name="max_percent" type="number" step="0.1" class="form-control form-control-sm" style="width: 5rem">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0" for="new-spims-{{ $source->id }}">{{ __('import.col_spims_letter') }}</label>
                <input id="new-spims-{{ $source->id }}" name="spims_letter" class="form-control form-control-sm" style="width: 4rem" required>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0" for="new-points-{{ $source->id }}">{{ __('import.col_gpa_points') }}</label>
                <input id="new-points-{{ $source->id }}" name="gpa_points" type="number" step="0.01" class="form-control form-control-sm" style="width: 5rem" required>
            </div>
            <div class="col-auto">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="is_passing" value="1" id="new-passing-{{ $source->id }}" checked>
                    <label class="form-check-label small" for="new-passing-{{ $source->id }}">{{ __('import.col_is_passing') }}</label>
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary">{{ __('import.add_grade_mapping') }}</button>
            </div>
        </form>
    </x-card>
@empty
    <x-empty-state :title="__('import.no_sources')" icon="bi-database" />
@endforelse

<x-card variant="panel">
    <h2 class="h6 page-title mb-3">{{ __('import.add_source') }}</h2>
    <form method="POST" action="{{ route('admin.imports.sources.store') }}" class="row g-3 academic-form">
        @csrf
        <div class="col-md-2">
            <label class="form-label" for="new-code">{{ __('import.source_code') }}</label>
            <input id="new-code" name="code" class="form-control text-uppercase" placeholder="POPULI" required>
            <p class="form-text mb-0">{{ __('import.source_code_help') }}</p>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="new-name">{{ __('import.source_name') }}</label>
            <input id="new-name" name="name" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="new-kind">{{ __('import.source_kind') }}</label>
            <select id="new-kind" name="kind" class="form-select">
                <option value="SIS">{{ __('import.source_kind_SIS') }}</option>
                <option value="LMS">{{ __('import.source_kind_LMS') }}</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label" for="new-precedence">{{ __('import.source_precedence') }}</label>
            <input id="new-precedence" name="precedence" type="number" min="1" max="99" value="1" class="form-control" required>
        </div>
        <div class="col-md-1">
            <label class="form-label" for="new-gpa">{{ __('import.source_gpa_scale') }}</label>
            <input id="new-gpa" name="gpa_scale_max" type="number" step="0.01" value="4.00" class="form-control" required>
        </div>
        <div class="col-md-1">
            <label class="form-label" for="new-currency">{{ __('import.source_currency') }}</label>
            <select id="new-currency" name="default_currency" class="form-select">
                <option value="EGP">EGP</option>
                <option value="USD">USD</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="new-tz">{{ __('import.source_timezone') }}</label>
            <input id="new-tz" name="timezone" class="form-control" value="Africa/Cairo" required>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">{{ __('import.save_source') }}</button>
        </div>
    </form>
</x-card>
@endsection
