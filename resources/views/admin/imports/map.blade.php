@extends('layouts.app')

@section('title', __('import.map_title'))

@section('content')
@php
    $profileByColumn = collect($batch->profile ?? [])->keyBy('column');
    $mapping = collect($batch->mapping ?? []);
    $dateFormats = ['d/m/Y' => 'd/m/Y', 'm/d/Y' => 'm/d/Y', 'Y-m-d' => 'Y-m-d'];
@endphp

<nav class="mb-3 small" aria-label="breadcrumb">
    <a href="{{ route('admin.imports.index') }}" class="text-decoration-none spims-text-dim">{{ __('import.title') }}</a>
    <span class="spims-text-dim mx-1">/</span>
    <span class="spims-text-dim">{{ __('import.map_title') }}</span>
</nav>

<x-page-header :title="__('import.map_title')" :subtitle="__('import.map_lead')" />

<div class="d-flex flex-wrap gap-2 mb-4">
    <span class="spims-status-badge spims-status-success">{{ __('import.step_upload') }}</span>
    <span class="spims-status-badge spims-status-info">{{ __('import.step_map') }}</span>
    <span class="spims-status-badge spims-status-neutral">{{ __('import.step_validate') }}</span>
</div>

@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

<x-card variant="quiet" class="mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label small fw-semibold" for="load-profile">{{ __('import.load_profile') }}</label>
            <form method="POST" action="{{ route('admin.imports.map.profile.apply', $batch) }}" class="d-flex gap-2">
                @csrf
                <select id="load-profile" name="profile_id" class="form-select form-select-sm" @if($profiles->isEmpty()) disabled @endif>
                    @forelse($profiles as $profile)
                        <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                    @empty
                        <option>{{ __('import.no_profiles') }}</option>
                    @endforelse
                </select>
                <button class="btn btn-sm btn-outline-primary text-nowrap" @if($profiles->isEmpty()) disabled @endif>{{ __('import.load_profile_button') }}</button>
            </form>
        </div>
        <div class="col-md-7">
            <label class="form-label small fw-semibold" for="save-profile-name">{{ __('import.save_profile') }}</label>
            <form method="POST" action="{{ route('admin.imports.save-profile', $batch) }}" class="d-flex gap-2">
                @csrf
                <input id="save-profile-name" name="name" class="form-control form-control-sm" placeholder="{{ __('import.save_profile_name') }}" required>
                <button class="btn btn-sm btn-outline-primary text-nowrap">{{ __('import.save_profile_button') }}</button>
            </form>
        </div>
    </div>
</x-card>

{{-- L8, Part B — "AI suggest" fills gaps only (columns still at None confidence);
     it never overrides a tier 1-3 match, and the button is inert unless
     import.ai_mapping_enabled is on. See docs/legacy-data-import-plan.md §22.3. --}}
<x-card variant="quiet" class="mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="h6 page-title mb-1">{{ __('import.ai_suggest_title') }}</h2>
            <p class="small spims-text-dim mb-0">{{ __('import.ai_suggest_masking_policy') }}</p>
        </div>
        @if($aiEnabled)
            <form method="POST" action="{{ route('admin.imports.map.ai-suggest', $batch) }}">
                @csrf
                <button class="btn btn-sm btn-outline-primary text-nowrap">{{ __('import.ai_suggest_button') }}</button>
            </form>
        @else
            <span tabindex="0" data-bs-toggle="tooltip" title="{{ __('import.ai_suggest_disabled_tooltip') }}">
                <button class="btn btn-sm btn-outline-secondary text-nowrap" disabled>{{ __('import.ai_suggest_button') }}</button>
            </span>
        @endif
    </div>
</x-card>

<form method="POST" action="{{ route('admin.imports.map.update', $batch) }}">
    @csrf

    @foreach($mapping as $i => $m)
        @php
            // A freshly suggested mapping (from ImportMappingSuggestionService) carries
            // column/target_field/transform/confidence/options but not yet 'ignored' —
            // that key only exists once an admin has saved the mapping form once.
            $m = array_merge(['target_field' => null, 'transform' => 'none', 'options' => [], 'ignored' => false, 'confidence' => 'None', 'origin' => null, 'rationale' => null], $m);
            $profile = $profileByColumn->get($m['column'], []);
        @endphp
        <x-card variant="panel" class="mb-3 {{ empty($m['target_field']) && empty($m['ignored']) ? 'border-danger' : '' }}"
            x-data="{ target: '{{ $m['target_field'] }}', transform: '{{ $m['transform'] }}', ignored: {{ $m['ignored'] ? 'true' : 'false' }} }">
            <input type="hidden" name="columns[{{ $i }}]" value="{{ $m['column'] }}">

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <strong>{{ $m['column'] }}</strong>
                        <span class="spims-status-badge spims-status-{{ ['High' => 'success', 'Medium' => 'info', 'Low' => 'warning', 'None' => 'danger'][$m['confidence'] ?? 'None'] }}">
                            {{ __('import.confidence_'.($m['confidence'] ?? 'None')) }}
                        </span>
                        @if(($m['origin'] ?? null) === 'AI')
                            <span class="spims-status-badge spims-status-neutral" title="{{ $m['rationale'] ?? '' }}">
                                {{ __('import.ai_origin_badge') }}
                            </span>
                        @endif
                    </div>
                    @if(($m['origin'] ?? null) === 'AI' && filled($m['rationale'] ?? null))
                        <p class="small spims-text-dim mb-1 fst-italic">{{ $m['rationale'] }}</p>
                    @endif
                    <p class="small spims-text-dim mb-1">
                        {{ $profile['type'] ?? '' }} &middot;
                        {{ __('import.empty_percent_label', ['percent' => $profile['empty_percent'] ?? 0]) }} &middot;
                        {{ __('import.distinct_label', ['count' => $profile['distinct_count'] ?? 0]) }}
                    </p>
                    <p class="small spims-text-dim mb-0">
                        {{ __('import.samples_label') }}: {{ implode(' · ', array_slice($profile['samples'] ?? [], 0, 3)) ?: '—' }}
                    </p>
                </div>

                <div class="col-md-4">
                    <label class="form-label small" for="target-{{ $i }}">{{ __('import.col_target_field') }}</label>
                    <select id="target-{{ $i }}" name="target_field[{{ $i }}]" class="form-select form-select-sm" x-model="target" :disabled="ignored">
                        <option value="">{{ __('import.not_mapped') }}</option>
                        @foreach($fields as $key => $label)
                            <option value="{{ $key }}">
                                {{ $label }}@if(in_array($key, $required, true)) &nbsp;{{ __('import.required_marker') }}@endif
                            </option>
                        @endforeach
                    </select>

                    <label class="form-label small mt-2" for="transform-{{ $i }}">{{ __('import.col_transform') }}</label>
                    <select id="transform-{{ $i }}" name="transform[{{ $i }}]" class="form-select form-select-sm" x-model="transform" :disabled="ignored">
                        @foreach($transforms as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <template x-if="transform === 'date'">
                        <div>
                            <label class="form-label small">{{ __('import.transform_option_date_format') }}</label>
                            <select name="options[{{ $i }}][format]" class="form-select form-select-sm">
                                @foreach($dateFormats as $fmt)
                                    <option value="{{ $fmt }}" @selected(($m['options']['format'] ?? 'd/m/Y') === $fmt)>{{ $fmt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>
                    <template x-if="transform === 'name_part_first' || transform === 'name_part_last'">
                        <div>
                            <label class="form-label small">{{ __('import.transform_option_name_order') }}</label>
                            <select name="options[{{ $i }}][format]" class="form-select form-select-sm">
                                <option value="last_first" @selected(($m['options']['format'] ?? 'last_first') === 'last_first')>{{ __('import.name_order_last_first') }}</option>
                                <option value="first_last" @selected(($m['options']['format'] ?? '') === 'first_last')>{{ __('import.name_order_first_last') }}</option>
                            </select>
                        </div>
                    </template>
                    <template x-if="transform === 'constant'">
                        <div>
                            <label class="form-label small">{{ __('import.transform_option_constant_value') }}</label>
                            <input name="options[{{ $i }}][value]" class="form-control form-control-sm" value="{{ $m['options']['value'] ?? '' }}">
                        </div>
                    </template>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="ignored[]" value="{{ $i }}" id="ignore-{{ $i }}" x-model="ignored">
                        <label class="form-check-label small" for="ignore-{{ $i }}">{{ __('import.col_ignore') }}</label>
                    </div>
                </div>
            </div>
        </x-card>
    @endforeach

    @if($missingFields !== [] || $unresolved)
        <div class="alert alert-danger">
            <h3 class="h6">{{ __('import.guard_title') }}</h3>
            @if($missingFields !== [])
                <p class="mb-1">{{ __('import.guard_missing', ['fields' => collect($missingFields)->map(fn ($f) => $fields[$f] ?? $f)->join(', ')]) }}</p>
            @endif
            @if($unresolved)
                <p class="mb-0">{{ __('import.guard_unresolved') }}</p>
            @endif
        </div>
    @else
        <div class="alert alert-success">
            <h3 class="h6">{{ __('import.guard_clear_title') }}</h3>
            <p class="mb-0">{{ __('import.guard_clear_body') }}</p>
        </div>
    @endif

    <div class="d-flex justify-content-between flex-wrap gap-2">
        <a href="{{ route('admin.imports.create') }}" class="btn btn-outline-secondary">{{ __('import.back_to_upload') }}</a>
        <button class="btn btn-primary btn-lg">{{ __('import.continue_button', ['count' => $batch->row_count]) }}</button>
    </div>
</form>
@endsection
