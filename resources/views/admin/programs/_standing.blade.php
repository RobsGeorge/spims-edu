@php
    $sourceLabel = ($standingThresholds['source'] ?? 'school') === 'program'
        ? __('reports.program_thresholds_source_program')
        : __('reports.program_thresholds_source_school');
    $formId = 'programStandingForm';
    $clearFormId = 'clearStandingForm';
@endphp
<x-card variant="panel" class="mb-4" id="standing">
    <h2 class="h5 spims-title mb-2">{{ __('reports.program_thresholds_title') }}</h2>
    <p class="spims-text-dim mb-2">{{ __('reports.program_thresholds_help') }}</p>
    <p class="spims-text-dim mb-2">{{ __('reports.program_thresholds_inherited', [
        'good_min' => $schoolThresholds['good_min'],
        'suspension_below' => $schoolThresholds['suspension_below'],
    ]) }}</p>
    <p class="spims-text-dim mb-3">{{ __('reports.program_thresholds_current', ['source' => $sourceLabel]) }}</p>

    @if(!empty($canManageStanding))
        <form method="POST" action="{{ route('admin.programs.standing.update', $program) }}" id="{{ $formId }}" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label class="form-label" for="standing_good_min">{{ __('reports.good_min') }}</label>
                <input
                    id="standing_good_min"
                    type="number"
                    min="0"
                    max="400"
                    step="1"
                    name="good_min"
                    class="form-control @error('good_min') is-invalid @enderror"
                    value="{{ old('good_min', $program->standing_good_min) }}"
                >
                @error('good_min')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="standing_suspension_below">{{ __('reports.suspension_below') }}</label>
                <input
                    id="standing_suspension_below"
                    type="number"
                    min="0"
                    max="400"
                    step="1"
                    name="suspension_below"
                    class="form-control @error('suspension_below') is-invalid @enderror"
                    value="{{ old('suspension_below', $program->standing_suspension_below) }}"
                >
                @error('suspension_below')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">{{ __('ui.save') }}</button>
                @if(($standingThresholds['source'] ?? 'school') === 'program')
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#clearStandingModal">
                        {{ __('reports.clear_override') }}
                    </button>
                @endif
            </div>
        </form>
    @endif
</x-card>

@if(!empty($canManageStanding) && ($standingThresholds['source'] ?? 'school') === 'program')
    <form method="POST" action="{{ route('admin.programs.standing.update', $program) }}" id="{{ $clearFormId }}">
        @csrf
        <input type="hidden" name="good_min" value="">
        <input type="hidden" name="suspension_below" value="">
    </form>
    <x-confirm-dialog
        id="clearStandingModal"
        :title="__('reports.clear_override_title')"
        :message="__('reports.clear_override_body')"
        tone="primary"
    >
        <x-slot:confirm>
            <button type="submit" form="{{ $clearFormId }}" class="btn btn-primary">{{ __('reports.clear_override') }}</button>
        </x-slot:confirm>
    </x-confirm-dialog>
@endif
