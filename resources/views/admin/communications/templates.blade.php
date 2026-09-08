@extends('layouts.app')
@section('title', __('communications.templates_title'))
@section('content')
<x-page-header :title="__('communications.templates_title')" :subtitle="__('communications.templates_sub')" />

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if($preview)
    <div class="alert alert-info">
        <div class="fw-semibold">{{ __('communications.preview_result') }}</div>
        <div>{{ $preview['subject'] }}</div>
        <pre class="mb-0 small">{{ $preview['body'] }}</pre>
    </div>
@endif

<form method="POST" action="{{ route('admin.email-templates.store') }}" class="app-card p-4 mb-4">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label">{{ __('communications.template_key') }}</label>
            <input name="key" class="form-control" value="{{ old('key', 'announcement.published') }}" required>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">{{ __('communications.template_locale') }}</label>
            <select name="locale" class="form-select">
                @foreach(['ar','en','fr'] as $code)
                    <option value="{{ $code }}" @selected(old('locale', app()->getLocale()) === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">{{ __('communications.template_subject') }}</label>
            <input name="subject" class="form-control" value="{{ old('subject') }}" required>
        </div>
        <div class="col-12">
            <label class="form-label">{{ __('communications.template_body') }}</label>
            <textarea name="body" class="form-control" rows="5" required>{{ old('body') }}</textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">{{ __('ui.save') }}</button>
        </div>
    </div>
</form>

<form method="POST" action="{{ route('admin.email-templates.preview') }}" class="app-card p-4 mb-4">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <input name="key" class="form-control" value="announcement.published" required>
        </div>
        <div class="col-12 col-md-2">
            <select name="locale" class="form-select">
                @foreach(['ar','en','fr'] as $code)
                    <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <input name="title" class="form-control" value="Preview title">
        </div>
        <div class="col-12 col-md-3">
            <button class="btn btn-outline-primary">{{ __('communications.preview') }}</button>
        </div>
    </div>
</form>

@foreach($templates as $template)
    <article class="border rounded-3 p-3 mb-2">
        <div class="fw-semibold">{{ $template->key }} · {{ $template->locale }}</div>
        <div class="small text-muted-theme">{{ $template->isGlobal() ? __('communications.template_global') : $template->scope_type }}</div>
        <div>{{ $template->subject }}</div>
    </article>
@endforeach
@endsection
