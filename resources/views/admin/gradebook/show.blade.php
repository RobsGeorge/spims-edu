@extends('layouts.app')
@section('title', __('assessment.gradebook'))
@section('content')
<x-page-header
    :title="__('assessment.gradebook').' — '.$offering->course->code"
    :subtitle="__('teach.tab_gradebook_help')"
>
    <x-slot:actions>
        <a href="{{ route('admin.gradebook.csv', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('assessment.export_csv') }}</a>
        <a href="{{ route('teach.show', ['offering' => $offering, 'tab' => 'gradebook']) }}" class="btn btn-outline-secondary btn-sm">{{ __('teach.workspace') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

{{-- Locked gradebook banner --}}
@if($offering->gradebook_locked_at)
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert" data-gradebook-locked-banner="1">
    <i class="bi bi-lock-fill fs-5 flex-shrink-0 mt-1" aria-hidden="true"></i>
    <div>
        <strong>{{ __('assessment.gradebook_locked_banner_title') }}</strong>
        <p class="mb-0">{{ __('assessment.gradebook_locked_banner_body', ['date' => $offering->gradebook_locked_at->isoFormat('LL LT')]) }}</p>
    </div>
</div>
@endif

<div class="d-flex gap-2 mb-3 flex-wrap">
    @unless($offering->gradebook_locked_at)
        <form method="POST" action="{{ route('admin.gradebook.seed', $offering) }}">@csrf<button class="btn btn-sm btn-outline-primary">{{ __('assessment.seed_template') }}</button></form>
        <form method="POST" action="{{ route('admin.gradebook.submit', $offering) }}">@csrf<button class="btn btn-sm btn-warning">{{ __('assessment.submit_grades') }}</button></form>
    @endunless
    @include('admin.gradebook._lock_reopen', ['offering' => $offering])
</div>

@unless($offering->gradebook_locked_at)
<form method="POST" action="{{ route('admin.gradebook.components', $offering) }}" class="row g-2 mb-3">@csrf
    <div class="col-12 col-md-3"><input name="name" class="form-control" placeholder="{{ __('assessment.component') }}" required></div>
    <div class="col-6 col-md-2"><input type="number" step="0.01" name="weight_percent" class="form-control" placeholder="%" required></div>
    <div class="col-6 col-md-3">
        <select name="kind" class="form-select" aria-label="{{ __('assessment.component') }}">
            @foreach($componentKinds as $kind)
                @php $kindVal = $kind->value; @endphp
                <option value="{{ $kindVal }}">{{ __('assessment.kind_'.$kindVal) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-2"><button class="btn btn-primary w-100 w-md-auto">{{ __('ui.save') }}</button></div>
</form>
@endunless

<p class="mb-2" data-weight-sum="{{ $weightSum }}">
    {{ __('assessment.weight_sum', ['sum' => $weightSum]) }}
</p>
@if(abs($weightSum - 100) > 0.01)
    <div class="alert alert-warning">{{ __('assessment.weights_not_100', ['sum' => $weightSum]) }}</div>
@endif

@include('admin.gradebook._grid', [
    'offering' => $offering,
    'components' => $components,
    'enrollments' => $enrollments,
    'gradeUrls' => $gradeUrls,
    'locked' => (bool) $offering->gradebook_locked_at,
])
@endsection
