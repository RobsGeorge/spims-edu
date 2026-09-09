@extends('layouts.app')

@section('title', isset($category) && $category ? __('help.categories.'.$category->slug) : __('help.title'))

@section('content')
@php
    $activeCategory = $category ?? null;
    $pageTitle = $activeCategory
        ? __('help.categories.'.$activeCategory->slug)
        : __('help.title');
    if ($activeCategory && $pageTitle === 'help.categories.'.$activeCategory->slug) {
        $pageTitle = $activeCategory->slug;
    }
    $listQuery = array_filter([
        'q' => ($searchQuery ?? '') !== '' ? $searchQuery : null,
        'role' => $roleFilter ?? null,
    ]);
@endphp
<div class="help-center animate-in" style="max-width:920px;margin:0 auto;">
    <x-page-header :title="$pageTitle" :subtitle="__('help.subtitle')" />

    <form method="get"
          action="{{ $activeCategory ? route('help.category', $activeCategory->slug) : route('help.index') }}"
          class="row g-2 align-items-end mb-4"
          role="search">
        <div class="col-sm">
            <label class="form-label visually-hidden" for="help-q">{{ __('help.search') }}</label>
            <input id="help-q"
                   type="search"
                   name="q"
                   value="{{ $searchQuery ?? '' }}"
                   class="form-control"
                   placeholder="{{ __('help.search_placeholder') }}">
        </div>
        @if($roleFilter)
            <input type="hidden" name="role" value="{{ $roleFilter }}">
        @endif
        <div class="col-sm-auto">
            <button type="submit" class="btn btn-primary w-100">{{ __('help.search') }}</button>
        </div>
    </form>

    @if(($categories ?? collect())->isNotEmpty())
        <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="{{ __('help.categories_nav') }}">
            <a href="{{ route('help.index', $listQuery) }}"
               class="btn btn-sm {{ $activeCategory ? 'btn-outline-secondary' : 'btn-secondary' }}">
                {{ __('help.all_categories') }}
            </a>
            @foreach($categories as $cat)
                @php
                    $catLabel = __('help.categories.'.$cat->slug);
                    if ($catLabel === 'help.categories.'.$cat->slug) {
                        $catLabel = $cat->slug;
                    }
                @endphp
                <a href="{{ route('help.category', array_merge(['category' => $cat->slug], $listQuery)) }}"
                   class="btn btn-sm {{ ($activeCategory?->slug ?? null) === $cat->slug ? 'btn-secondary' : 'btn-outline-secondary' }}">
                    {{ $catLabel }}
                </a>
            @endforeach
        </nav>
    @endif

    @if(($roleChips ?? collect())->isNotEmpty())
        <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="{{ __('help.role_guides') }}">
            @php
                $clearRoleQuery = array_filter(['q' => ($searchQuery ?? '') !== '' ? $searchQuery : null]);
            @endphp
            <a href="{{ $activeCategory ? route('help.category', array_merge(['category' => $activeCategory->slug], $clearRoleQuery)) : route('help.index', $clearRoleQuery) }}"
               class="btn btn-sm {{ $roleFilter ? 'btn-outline-secondary' : 'btn-secondary' }}">
                {{ __('help.all_roles') }}
            </a>
            @foreach($roleChips as $chip)
                @php
                    $chipLabel = __('roles_hub.role_'.$chip->value);
                    if ($chipLabel === 'roles_hub.role_'.$chip->value) {
                        $chipLabel = __('help.audience_'.$chip->value);
                    }
                    $chipQuery = array_filter([
                        'q' => ($searchQuery ?? '') !== '' ? $searchQuery : null,
                        'role' => $chip->value,
                    ]);
                @endphp
                <a href="{{ $activeCategory ? route('help.category', array_merge(['category' => $activeCategory->slug], $chipQuery)) : route('help.index', $chipQuery) }}"
                   class="btn btn-sm {{ $roleFilter === $chip->value ? 'btn-secondary' : 'btn-outline-secondary' }}">
                    {{ $chipLabel }}
                </a>
            @endforeach
        </div>
    @endif

    @if($roleFilter)
        <p class="text-muted-theme mb-3">
            {{ __('help.role_filter', ['role' => $roleLabel ?? $roleFilter]) }}
            · <a href="{{ $activeCategory ? route('help.category', $activeCategory->slug) : route('help.index') }}">{{ __('help.back_to_index') }}</a>
        </p>
    @endif

    @if(($articles ?? collect())->isEmpty())
        <x-empty-state :title="__('help.empty')" icon="bi-question-circle">
            <x-slot:actions>
                <a href="{{ route('help.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('help.back_to_index') }}</a>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="list-group help-article-list">
            @foreach($articles as $article)
                @php
                    $row = $article->localeFor($locale ?? app()->getLocale());
                    $title = $row?->title ?? $article->slug;
                    $summary = $row?->summary ?? '';
                @endphp
                <a href="{{ route('help.show', $article->slug) }}"
                   class="list-group-item list-group-item-action app-card border-0 mb-2 rounded-3 px-3 py-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                        <div>
                            <h2 class="h6 spims-title mb-1">{{ $title }}</h2>
                            @if($summary !== '')
                                <p class="text-muted-theme small mb-0">{{ $summary }}</p>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @include('help.partials.audience-badges', ['article' => $article])
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
