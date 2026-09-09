@extends('layouts.app')

@section('title', __('help.admin_categories'))

@section('content')
<x-page-header :title="__('help.admin_title')" :subtitle="__('help.admin_subtitle')" />

@include('admin.help._nav', ['helpAdminTab' => 'categories'])

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">{{ __('help.admin_new_category') }}</h2>
        <form method="POST" action="{{ route('admin.help.categories.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label">{{ __('help.admin_slug') }}</label>
                <input name="slug" class="form-control" value="{{ old('slug') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('help.admin_sort_order') }}</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="is_published" value="1" id="catPublished" @checked(old('is_published', true))>
                    <label class="form-check-label" for="catPublished">{{ __('help.admin_published_flag') }}</label>
                </div>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary">{{ __('ui.save') }}</button>
            </div>
        </form>
    </div>
</div>

@if($categories->isEmpty())
    <x-empty-state :title="__('help.admin_categories_empty')" icon="bi-folder" />
@else
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('help.admin_slug') }}</th>
                    <th>{{ __('help.admin_sort_order') }}</th>
                    <th>{{ __('help.admin_published_flag') }}</th>
                    <th>{{ __('help.admin_article_count') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($categories as $category)
                @php
                    $label = __('help.categories.'.$category->slug);
                    if ($label === 'help.categories.'.$category->slug) {
                        $label = $category->slug;
                    }
                @endphp
                <tr>
                    <td>
                        <strong>{{ $label }}</strong>
                        <div class="text-muted-theme small">{{ $category->slug }}</div>
                    </td>
                    <td>{{ $category->sort_order }}</td>
                    <td>
                        <x-status-badge
                            :status="$category->is_published ? 'success' : 'neutral'"
                            :label="$category->is_published ? __('help.admin_published_flag') : __('help.status_draft')"
                        />
                    </td>
                    <td>{{ $category->articles_count }}</td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.help.categories.edit', $category) }}">{{ __('help.admin_edit') }}</a>
                        <form method="POST" action="{{ route('admin.help.categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('{{ __('help.admin_delete') }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('help.admin_delete') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
