@extends('layouts.app')
@section('title', __('completion.templates_title'))
@section('content')
<x-page-header :title="__('completion.templates_title')" />
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

<form method="POST" action="{{ route('admin.certificate-templates.store') }}" class="card border-0 shadow-sm mb-4">
    @csrf
    <div class="card-body row g-2">
        <div class="col-md-3">
            <select name="course_id" class="form-select" aria-label="{{ __('completion.scope') }}">
                <option value="">{{ __('completion.scope_global') }}</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="locale" class="form-select" required>
                <option value="ar">ar</option>
                <option value="en" selected>en</option>
                <option value="fr">fr</option>
            </select>
        </div>
        <div class="col-md-7"><input name="title" class="form-control" required placeholder="{{ __('completion.template_title') }}"></div>
        <div class="col-12"><textarea name="body" class="form-control" rows="4" required placeholder="{{ __('completion.template_body') }}"></textarea></div>
        <div class="col-md-3"><button class="btn btn-primary">{{ __('completion.save_template') }}</button></div>
    </div>
</form>

<form method="GET" action="{{ route('admin.certificate-templates.preview') }}" class="mb-4">
    <div class="row g-2">
        <div class="col-md-3">
            <select name="course_id" class="form-select">
                <option value="">{{ __('completion.scope_global') }}</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="locale" class="form-select">
                <option value="ar">ar</option>
                <option value="en" selected>en</option>
                <option value="fr">fr</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary">{{ __('completion.preview') }}</button></div>
    </div>
</form>

<div class="table-responsive spims-table-wrap">
<table class="table table-sm">
    <thead><tr><th>{{ __('completion.scope') }}</th><th>{{ __('completion.locale') }}</th><th>{{ __('completion.template_title') }}</th></tr></thead>
    <tbody>
    @forelse($templates as $template)
        <tr>
            <td>{{ $template->course?->code ?? __('completion.scope_global') }}</td>
            <td>{{ $template->locale }}</td>
            <td>{{ $template->title }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="text-muted-theme">{{ __('completion.no_templates') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
