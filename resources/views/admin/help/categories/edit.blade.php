@extends('layouts.app')

@section('title', __('help.admin_edit_category'))

@section('content')
<x-page-header :title="__('help.admin_edit_category')" :subtitle="$category->slug" />

@include('admin.help._nav', ['helpAdminTab' => 'categories'])

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.help.categories.update', $category) }}" class="row g-3">
            @csrf
            @method('PUT')
            <div class="col-md-4">
                <label class="form-label">{{ __('help.admin_slug') }}</label>
                <input name="slug" class="form-control" value="{{ old('slug', $category->slug) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('help.admin_sort_order') }}</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $category->sort_order) }}" min="0">
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="is_published" value="1" id="catPublished"
                           @checked(old('is_published', $category->is_published))>
                    <label class="form-check-label" for="catPublished">{{ __('help.admin_published_flag') }}</label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary">{{ __('ui.save') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.help.categories.index') }}">{{ __('help.back_to_index') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
