@extends('layouts.app')

@section('title', __('hubs.grading_schemes'))

@section('content')
<x-page-header :title="__('hubs.grading_schemes')" :subtitle="__('hubs.grading_schemes_desc')" />

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('academics.create_grading_scheme') }}</h2>
        <form method="POST" action="{{ route('admin.grading-schemes.store') }}">
            @csrf
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('academics.name') }}</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="col-md-3 form-check mt-4">
                    <input type="checkbox" name="is_default" value="1" class="form-check-input" id="scheme_default_new">
                    <label for="scheme_default_new" class="form-check-label">{{ __('academics.is_default') }}</label>
                </div>
            </div>
            <p class="small text-muted-theme mb-2">{{ __('academics.bands') }}</p>
            @foreach([['A', 90, 100, 4, 1], ['B', 80, 89.99, 3, 1], ['C', 70, 79.99, 2, 1], ['D', 60, 69.99, 1, 1], ['F', 0, 59.99, 0, 0]] as $i => $defaults)
                <div class="row g-2 mb-2 align-items-end">
                    <div class="col-md-2">
                        <input name="bands[{{ $i }}][letter]" class="form-control" value="{{ $defaults[0] }}" required placeholder="{{ __('academics.letter') }}">
                    </div>
                    <div class="col-md-2">
                        <input type="number" step="0.01" name="bands[{{ $i }}][min_percent]" class="form-control" value="{{ $defaults[1] }}" required placeholder="{{ __('academics.min_percent') }}">
                    </div>
                    <div class="col-md-2">
                        <input type="number" step="0.01" name="bands[{{ $i }}][max_percent]" class="form-control" value="{{ $defaults[2] }}" required placeholder="{{ __('academics.max_percent') }}">
                    </div>
                    <div class="col-md-2">
                        <input type="number" step="0.01" name="bands[{{ $i }}][gpa_points]" class="form-control" value="{{ $defaults[3] }}" required placeholder="{{ __('academics.gpa_points') }}">
                    </div>
                    <div class="col-md-2 form-check ms-2">
                        <input type="checkbox" name="bands[{{ $i }}][is_passing]" value="1" class="form-check-input" id="band_pass_new_{{ $i }}" @checked($defaults[4])>
                        <label for="band_pass_new_{{ $i }}" class="form-check-label">{{ __('academics.is_passing') }}</label>
                    </div>
                </div>
            @endforeach
            <button class="btn btn-primary">{{ __('ui.save') }}</button>
        </form>
    </div>
</div>

@foreach($schemes as $scheme)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">
                {{ $scheme->name }}
                @if($scheme->is_default)
                    <x-status-badge status="success" :label="__('academics.is_default')" />
                @endif
            </h2>
            <form method="POST" action="{{ route('admin.grading-schemes.update', $scheme) }}">
                @csrf
                @method('PUT')
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('academics.name') }}</label>
                        <input name="name" class="form-control" value="{{ $scheme->name }}" required>
                    </div>
                    <div class="col-md-3 form-check mt-4">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" class="form-check-input" id="scheme_default_{{ $scheme->id }}" @checked($scheme->is_default)>
                        <label for="scheme_default_{{ $scheme->id }}" class="form-check-label">{{ __('academics.is_default') }}</label>
                    </div>
                </div>
                @foreach($scheme->bands->sortByDesc('min_percent')->values() as $i => $band)
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-2">
                            <input name="bands[{{ $i }}][letter]" class="form-control" value="{{ $band->letter }}" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" step="0.01" name="bands[{{ $i }}][min_percent]" class="form-control" value="{{ $band->min_percent }}" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" step="0.01" name="bands[{{ $i }}][max_percent]" class="form-control" value="{{ $band->max_percent }}" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" step="0.01" name="bands[{{ $i }}][gpa_points]" class="form-control" value="{{ $band->gpa_points }}" required>
                        </div>
                        <div class="col-md-2 form-check ms-2">
                            <input type="checkbox" name="bands[{{ $i }}][is_passing]" value="1" class="form-check-input" id="band_pass_{{ $scheme->id }}_{{ $i }}" @checked($band->is_passing)>
                            <label for="band_pass_{{ $scheme->id }}_{{ $i }}" class="form-check-label">{{ __('academics.is_passing') }}</label>
                        </div>
                    </div>
                @endforeach
                <button class="btn btn-primary btn-sm">{{ __('ui.save_changes') }}</button>
            </form>
        </div>
    </div>
@endforeach
@endsection
