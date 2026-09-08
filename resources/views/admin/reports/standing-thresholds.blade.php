@extends('layouts.app')
@section('title', __('reports.thresholds_title'))
@section('content')
<div class="hub-page animate-in">
    <x-page-header :title="__('reports.thresholds_title')" :subtitle="__('reports.thresholds_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.reports.standing') }}" class="btn btn-outline-secondary">{{ __('reports.standing_title') }}</a>
            <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">{{ __('reports.back_hub') }}</a>
        </x-slot:actions>
    </x-page-header>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.reports.standing.thresholds.update') }}" class="app-card p-4">
        @csrf
        <p class="text-muted-theme mb-4">{{ __('reports.thresholds_help') }}</p>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="good_min">{{ __('reports.good_min') }}</label>
                <input
                    id="good_min"
                    type="number"
                    min="0"
                    max="400"
                    step="1"
                    name="good_min"
                    class="form-control @error('good_min') is-invalid @enderror"
                    value="{{ old('good_min', $thresholds['good_min']) }}"
                    required
                >
                @error('good_min')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="suspension_below">{{ __('reports.suspension_below') }}</label>
                <input
                    id="suspension_below"
                    type="number"
                    min="0"
                    max="400"
                    step="1"
                    name="suspension_below"
                    class="form-control @error('suspension_below') is-invalid @enderror"
                    value="{{ old('suspension_below', $thresholds['suspension_below']) }}"
                    required
                >
                @error('suspension_below')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">{{ __('ui.save') }}</button>
            </div>
        </div>
    </form>

    <div class="app-card p-4 mt-4">
        <h2 class="h5 spims-title mb-2">{{ __('reports.program_overrides_title') }}</h2>
        <p class="text-muted-theme mb-3">{{ __('reports.program_overrides_desc') }}</p>
        <div class="table-responsive spims-table-wrap">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('reports.col_program_code') }}</th>
                        <th>{{ __('reports.col_program_name') }}</th>
                        <th>{{ __('reports.program_thresholds_source') }}</th>
                        <th>{{ __('reports.good_min') }}</th>
                        <th>{{ __('reports.suspension_below') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($programThresholds as $row)
                    <tr>
                        <td>{{ $row['program']->code }}</td>
                        <td>{{ $row['program']->name }}</td>
                        <td>
                            {{ $row['thresholds']['source'] === 'program'
                                ? __('reports.program_thresholds_source_program')
                                : __('reports.program_thresholds_source_school') }}
                        </td>
                        <td>{{ $row['thresholds']['good_min'] }}</td>
                        <td>{{ $row['thresholds']['suspension_below'] }}</td>
                        <td>
                            <a href="{{ route('admin.programs.show', $row['program']) }}#standing" class="btn btn-sm btn-outline-primary">
                                {{ __('reports.edit_program_thresholds') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted-theme">{{ __('reports.program_overrides_empty') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
