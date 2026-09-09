@extends('layouts.app')

@section('title', __('help.admin_articles'))

@section('content')
<x-page-header :title="__('help.admin_title')" :subtitle="__('help.admin_subtitle')">
    <x-slot:actions>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.help.articles.create') }}">{{ __('help.admin_new_article') }}</a>
    </x-slot:actions>
</x-page-header>

@include('admin.help._nav', ['helpAdminTab' => 'articles'])

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.help.articles.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">{{ __('help.admin_status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('help.admin_all') }}</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>
                            {{ __('help.status_'.$status->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('help.admin_category') }}</label>
                <select name="category_id" class="form-select">
                    <option value="">{{ __('help.admin_all') }}</option>
                    @foreach($categories as $category)
                        @php
                            $label = __('help.categories.'.$category->slug);
                            if ($label === 'help.categories.'.$category->slug) {
                                $label = $category->slug;
                            }
                        @endphp
                        <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('help.admin_audiences') }}</label>
                <select name="audience" class="form-select">
                    <option value="">{{ __('help.admin_all') }}</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->value }}" @selected($filters['audience'] === $role->value)>
                            {{ __('help.audience_'.$role->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('help.admin_locale_completeness') }}</label>
                <select name="locale_completeness" class="form-select">
                    <option value="">{{ __('help.admin_all') }}</option>
                    <option value="complete" @selected($filters['locale_completeness'] === 'complete')>{{ __('help.admin_locale_complete') }}</option>
                    <option value="incomplete" @selected($filters['locale_completeness'] === 'incomplete')>{{ __('help.admin_locale_incomplete') }}</option>
                    <option value="missing_ar" @selected($filters['locale_completeness'] === 'missing_ar')>{{ __('help.admin_locale_missing_ar') }}</option>
                    <option value="missing_fr" @selected($filters['locale_completeness'] === 'missing_fr')>{{ __('help.admin_locale_missing_fr') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('help.admin_search') }}</label>
                <input name="q" class="form-control" value="{{ $filters['q'] }}">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-primary w-100">{{ __('help.admin_filter') }}</button>
            </div>
        </form>
    </div>
</div>

@if($articles->isEmpty())
    <x-empty-state :title="__('help.admin_articles_empty')" icon="bi-journal-text" />
@else
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('help.admin_title_field') }}</th>
                    <th>{{ __('help.admin_slug') }}</th>
                    <th>{{ __('help.admin_category') }}</th>
                    <th>{{ __('help.admin_status') }}</th>
                    <th>{{ __('help.admin_locale_completeness') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($articles as $article)
                @php
                    $en = $article->locales->firstWhere('locale', 'en');
                    $missing = $missingByArticle[$article->id] ?? [];
                @endphp
                <tr>
                    <td>{{ $en?->title ?: '—' }}</td>
                    <td><code class="small">{{ $article->slug }}</code></td>
                    <td>{{ $article->category?->slug }}</td>
                    <td>
                        <x-status-badge
                            :status="$article->status->value === 'published' ? 'success' : ($article->status->value === 'archived' ? 'neutral' : 'info')"
                            :label="__('help.status_'.$article->status->value)"
                        />
                    </td>
                    <td>
                        @if($missing === [])
                            <span class="text-success small">en · ar · fr</span>
                        @else
                            <span class="text-warning small">{{ __('help.admin_missing_locales', ['locales' => implode(', ', $missing)]) }}</span>
                        @endif
                    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.help.articles.edit', $article) }}">{{ __('help.admin_edit') }}</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
{{ $articles->links() }}
@endif
@endsection
