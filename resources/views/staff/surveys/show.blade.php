@extends('layouts.app')
@section('title', $survey->title)
@section('content')
<x-page-header
    :title="$survey->title"
    :subtitle="$offering ? ($offering->course->code.' — '.$offering->course->title) : __('staff.surveys.school_wide')"
    :eyebrow="__('staff.surveys.eyebrow')"
>
    <x-slot:actions>
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary btn-sm">{{ __('staff.surveys.back_list') }}</a>
        <a href="{{ $reportRoute }}" class="btn btn-outline-primary btn-sm">{{ __('staff.surveys.report') }}</a>
    </x-slot:actions>
</x-page-header>

@if($offering)
    @include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'surveys', 'prefix' => 'teach'])
@endif

<div class="d-flex flex-wrap gap-2 mb-3 mt-3">
    <x-status-badge :status="$survey->status->value" :label="$survey->statusLabel()" />
    @if($survey->isDraft())
        <form method="POST" action="{{ $publishRoute }}">
            @csrf
            <button class="btn btn-sm btn-primary">{{ __('staff.surveys.publish') }}</button>
        </form>
    @elseif($survey->isPublished())
        <form method="POST" action="{{ $closeRoute }}">
            @csrf
            <button class="btn btn-sm btn-outline-danger">{{ __('staff.surveys.close') }}</button>
        </form>
    @endif
</div>

@if($survey->isDraft())
    <form method="POST" action="{{ $questionRoute }}" class="row g-2 mb-4">
        @csrf
        <div class="col-12 col-md-5"><input name="prompt" class="form-control" required placeholder="{{ __('staff.surveys.prompt') }}"></div>
        <div class="col-12 col-md-2">
            <select name="kind" class="form-select" aria-label="{{ __('staff.surveys.kind') }}">
                @foreach($kinds as $kind)
                    <option value="{{ $kind->value }}">{{ __('staff.surveys.kind_'.$kind->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3"><input name="options_text" class="form-control" placeholder="{{ __('staff.surveys.options_hint') }}"></div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100">{{ __('staff.surveys.add_question') }}</button></div>
        <div class="col-12">
            <label class="form-check">
                <input type="checkbox" name="required" value="1" class="form-check-input" checked>
                <span class="form-check-label">{{ __('staff.surveys.required') }}</span>
            </label>
        </div>
    </form>
@endif

@forelse($survey->questions as $question)
    <div class="border rounded-3 p-3 mb-2">
        <strong>{{ $question->position }}. {{ $question->prompt }}</strong>
        <div class="small text-muted-theme">
            {{ __('staff.surveys.kind_'.$question->kind->value) }}
            @if($question->required)
                · {{ __('staff.surveys.required') }}
            @endif
            @if(is_array($question->options) && $question->options !== [])
                · {{ implode(', ', $question->options) }}
            @endif
        </div>
    </div>
@empty
    <x-empty-state :title="__('staff.surveys.no_questions')" icon="bi-list-ul" />
@endforelse
@endsection
