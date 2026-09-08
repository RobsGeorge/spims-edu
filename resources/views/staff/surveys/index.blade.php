@extends('layouts.app')
@section('title', __('staff.surveys.title'))
@section('content')
<x-page-header
    :title="__('staff.surveys.title')"
    :subtitle="$offering ? ($offering->course->code.' — '.$offering->course->title) : __('staff.surveys.school_wide')"
    :eyebrow="$offering ? __('teach.workspace') : __('staff.surveys.eyebrow_admin')"
>
    <x-slot:actions>
        @if($offering)
            <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'surveys']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.back') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

@if($offering)
    @include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'surveys', 'prefix' => 'teach'])
@endif

<form method="POST" action="{{ $storeRoute }}" class="row g-2 mb-4 mt-3">
    @csrf
    <div class="col-md-4"><input name="title" class="form-control" required placeholder="{{ __('staff.surveys.title_placeholder') }}"></div>
    <div class="col-md-3"><input type="datetime-local" name="opens_at" class="form-control" aria-label="{{ __('staff.surveys.opens_at') }}"></div>
    <div class="col-md-3"><input type="datetime-local" name="closes_at" class="form-control" aria-label="{{ __('staff.surveys.closes_at') }}"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('staff.surveys.create') }}</button></div>
    <div class="col-12">
        <label class="form-check">
            <input type="checkbox" name="anonymous_default" value="1" class="form-check-input" checked>
            <span class="form-check-label">{{ __('staff.surveys.anonymous_default') }}</span>
        </label>
    </div>
</form>

@forelse($surveys as $survey)
    <div class="spims-staff-row border rounded-3 p-3 mb-2">
        <div>
            <strong>{{ $survey->title }}</strong>
            <div class="small spims-text-dim">
                <x-status-badge :status="$survey->status->value" :label="$survey->statusLabel()" />
                {{ $survey->questions_count }} {{ __('staff.surveys.questions') }}
            </div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ $showRoute($survey) }}">{{ __('staff.surveys.manage') }}</a>
    </div>
@empty
    <x-empty-state :title="__('staff.surveys.empty')" icon="bi-clipboard-data" />
@endforelse
@endsection
