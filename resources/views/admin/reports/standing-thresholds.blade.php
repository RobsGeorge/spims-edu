@extends('layouts.app')
@section('title', __('reports.thresholds_title'))
@section('content')
<div class="hub-page animate-in" style="max-width:720px;margin:0 auto;">
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
            <div class="col-md-6">
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
            <div class="col-md-6">
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
</div>
@endsection
