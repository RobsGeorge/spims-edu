@extends('layouts.app')

@section('title', __('import.new_title'))

@section('content')
<nav class="mb-3 small" aria-label="breadcrumb">
    <a href="{{ route('admin.imports.index') }}" class="text-decoration-none spims-text-dim">{{ __('import.title') }}</a>
    <span class="spims-text-dim mx-1">/</span>
    <span class="spims-text-dim">{{ __('import.new_title') }}</span>
</nav>

<x-page-header :title="__('import.new_title')" />

<div class="d-flex flex-wrap gap-2 mb-4" role="list" aria-label="{{ __('import.new_title') }}">
    <span class="spims-status-badge spims-status-info">{{ __('import.step_upload') }}</span>
    <span class="spims-status-badge spims-status-neutral">{{ __('import.step_map') }}</span>
    <span class="spims-status-badge spims-status-neutral">{{ __('import.step_validate') }}</span>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<x-card variant="quiet" class="mb-4">
    <h2 class="h6 page-title mb-2">{{ __('import.upload_help_title') }}</h2>
    <p class="small spims-text-dim mb-0">{{ __('import.upload_help_body') }}</p>
</x-card>

<x-card variant="panel">
    <p class="small spims-text-dim mb-4">{{ __('import.upload_lead') }}</p>

    @if($sources->isEmpty())
        <x-empty-state :title="__('import.no_sources')" icon="bi-database">
            <x-slot:actions>
                <a href="{{ route('admin.imports.sources.index') }}" class="btn btn-primary btn-sm">{{ __('import.add_source') }}</a>
            </x-slot:actions>
        </x-empty-state>
    @else
        <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="row g-4"
            x-data="{ entityType: '{{ old('entity_type', 'STUDENT') }}' }">
            @csrf

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="source_id">{{ __('import.field_source') }}</label>
                <select id="source_id" name="source_id" class="form-select" required>
                    @foreach($sources as $source)
                        <option value="{{ $source->id }}" @selected(old('source_id') == $source->id)>{{ $source->name }} ({{ $source->code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="entity_type">{{ __('import.field_entity_type') }}</label>
                <select id="entity_type" name="entity_type" class="form-select" x-model="entityType" required>
                    @foreach($entityTypes as $type)
                        @php $typeValue = $type->value; @endphp
                        <option value="{{ $typeValue }}" @selected(old('entity_type', 'STUDENT') === $typeValue)>{{ __('import.entity_'.$typeValue) }}</option>
                    @endforeach
                </select>
                <p class="form-text mb-0">{{ __('import.field_entity_type_help') }}</p>
            </div>

            <div class="col-12" x-show="entityType === 'STUDENT'">
                <span class="form-label fw-semibold d-block">{{ __('import.field_population') }}</span>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="population" id="pop-alumni" value="ALUMNI" @checked(old('population', 'ALUMNI') === 'ALUMNI')>
                    <label class="form-check-label" for="pop-alumni">
                        {{ __('import.population_ALUMNI') }}
                        <span class="d-block small spims-text-dim">{{ __('import.population_alumni_help') }}</span>
                    </label>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="radio" name="population" id="pop-active" value="ACTIVE" @checked(old('population') === 'ACTIVE')>
                    <label class="form-check-label" for="pop-active">
                        {{ __('import.population_ACTIVE') }}
                        <span class="d-block small spims-text-dim">{{ __('import.population_active_help') }}</span>
                    </label>
                </div>
            </div>

            <div class="col-12" x-show="entityType === 'COURSE_RESULT'">
                <x-card variant="quiet">
                    <p class="small spims-text-dim mb-0">{{ __('import.course_result_help') }}</p>
                </x-card>
            </div>

            <div class="col-12" x-show="entityType === 'MIDTERM_ENROLLMENT'">
                <x-card variant="quiet">
                    <p class="small spims-text-dim mb-0">{{ __('import.midterm_enrollment_help') }}</p>
                </x-card>
            </div>

            <div class="col-12" x-show="entityType === 'BALANCE'">
                <x-card variant="quiet">
                    <h2 class="h6 page-title mb-2">{{ __('import.balance_totals_title') }}</h2>
                    <p class="small spims-text-dim mb-3">{{ __('import.balance_totals_help') }}</p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small" for="as_of">{{ __('import.field_as_of') }}</label>
                            <input id="as_of" name="as_of" type="date" class="form-control form-control-sm" value="{{ old('as_of') }}">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <label class="form-label small" for="declared_owed_EGP">{{ __('import.field_declared_owed_egp') }}</label>
                            <input id="declared_owed_EGP" name="declared_owed_EGP" class="form-control form-control-sm" placeholder="0.00" value="{{ old('declared_owed_EGP') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="declared_credit_EGP">{{ __('import.field_declared_credit_egp') }}</label>
                            <input id="declared_credit_EGP" name="declared_credit_EGP" class="form-control form-control-sm" placeholder="0.00" value="{{ old('declared_credit_EGP') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="declared_owed_USD">{{ __('import.field_declared_owed_usd') }}</label>
                            <input id="declared_owed_USD" name="declared_owed_USD" class="form-control form-control-sm" placeholder="0.00" value="{{ old('declared_owed_USD') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="declared_credit_USD">{{ __('import.field_declared_credit_usd') }}</label>
                            <input id="declared_credit_USD" name="declared_credit_USD" class="form-control form-control-sm" placeholder="0.00" value="{{ old('declared_credit_USD') }}">
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="col-12">
                <details>
                    <summary class="small fw-semibold" style="cursor:pointer">{{ __('import.field_sheet_name') }} / {{ __('import.field_header_row') }}</summary>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label" for="sheet_name">{{ __('import.field_sheet_name') }}</label>
                            <input id="sheet_name" name="sheet_name" class="form-control" value="{{ old('sheet_name') }}">
                            <p class="form-text mb-0">{{ __('import.field_sheet_name_help') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="header_row">{{ __('import.field_header_row') }}</label>
                            <input id="header_row" name="header_row" type="number" min="1" max="20" class="form-control" value="{{ old('header_row', 1) }}">
                            <p class="form-text mb-0">{{ __('import.field_header_row_help') }}</p>
                        </div>
                    </div>
                </details>
            </div>

            <div class="col-12">
                <span class="form-label fw-semibold d-block">{{ __('import.field_file') }}</span>
                <x-file-drop name="file" accept=".csv,.xlsx,.xls" :max-size="20971520" />
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-lg">{{ __('import.upload_button') }}</button>
            </div>
        </form>
    @endif
</x-card>
@endsection
