@extends('layouts.app')
@section('title', __('ui.nav_catalog'))
@section('content')
<div class="animate-in">
    <x-page-header :title="__('ui.nav_catalog')" :subtitle="__('hubs.catalog_desc')" icon="catalog" />

    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    @if(!empty($featured) && empty($showSkeletons))
        @php
            $featuredOffering = $offeringsByCourse->get($featured->id)?->first();
        @endphp
        <section class="catalog-featured" aria-labelledby="catalog-featured-title">
            <div class="catalog-featured-copy">
                <p class="catalog-featured-chip">{{ __('catalog.featured_chip') }}</p>
                <h2 id="catalog-featured-title" class="catalog-featured-title">{{ $featured->title }}</h2>
                <p class="text-muted-theme mb-3">{{ $featured->code }} · {{ __('catalog.credits', ['count' => $featured->credit_hours]) }}</p>
                <div class="d-flex flex-wrap gap-2">
                    @if($featuredOffering)
                        <a class="btn btn-primary" href="{{ route('offerings.preview', $featuredOffering) }}">{{ __('catalog.preview') }}</a>
                    @endif
                    <a class="btn btn-outline-primary" href="#catalog-results">{{ __('catalog.browse_all') }}</a>
                </div>
            </div>
            <x-course-cover :course="$featured" class="catalog-featured-media" />
        </section>
    @endif

    <form method="GET" action="{{ route('catalog.index') }}" class="catalog-filters app-card p-3 mb-4" data-catalog-loading aria-controls="catalog-results">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label" for="catalog-q">{{ __('catalog.search_placeholder') }}</label>
                <input id="catalog-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('catalog.search_placeholder') }}">
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="catalog-type">{{ __('catalog.programs') }}</label>
                <select id="catalog-type" name="type" class="form-select">
                    <option value="all" @selected($filters['type']==='all')>{{ __('catalog.filter_all') }}</option>
                    <option value="standalone" @selected($filters['type']==='standalone')>{{ __('catalog.filter_standalone') }}</option>
                    <option value="program" @selected($filters['type']==='program')>{{ __('catalog.filter_program') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="catalog-price">{{ __('catalog.filter_paid') }}</label>
                <select id="catalog-price" name="price" class="form-select">
                    <option value="all" @selected($filters['price']==='all')>{{ __('catalog.filter_all') }}</option>
                    <option value="free" @selected($filters['price']==='free')>{{ __('catalog.filter_free') }}</option>
                    <option value="paid" @selected($filters['price']==='paid')>{{ __('catalog.filter_paid') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="catalog-sort">{{ __('catalog.sort') }}</label>
                <select id="catalog-sort" name="sort" class="form-select">
                    <option value="code" @selected($filters['sort']==='code')>{{ __('catalog.sort_code') }}</option>
                    <option value="interest" @selected($filters['sort']==='interest')>{{ __('catalog.sort_interest') }}</option>
                </select>
            </div>
            @auth
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label" for="catalog-interest">{{ __('catalog.interest_filter') }}</label>
                    <select id="catalog-interest" name="interest" class="form-select">
                        <option value="all" @selected($filters['interest']==='all')>{{ __('catalog.interest_all') }}</option>
                        <option value="flagged" @selected($filters['interest']==='flagged')>{{ __('catalog.interest_flagged') }}</option>
                    </select>
                </div>
            @endauth
            <div class="col-12 col-lg-1">
                <button class="btn btn-primary w-100">{{ __('ui.continue') }}</button>
            </div>
        </div>
    </form>

    <div id="catalog-skeletons" class="catalog-skeletons mb-4" @if(empty($showSkeletons)) hidden @endif aria-hidden="{{ empty($showSkeletons) ? 'true' : 'false' }}" aria-label="{{ __('catalog.loading') }}">
        @for($i = 0; $i < 6; $i++)
            <div class="catalog-skeleton-card">
                <div class="catalog-skeleton-media"></div>
                <div class="catalog-skeleton-body">
                    <div class="catalog-skeleton-line catalog-skeleton-line--title"></div>
                    <div class="catalog-skeleton-line catalog-skeleton-line--meta"></div>
                    <div class="catalog-skeleton-line catalog-skeleton-line--body"></div>
                    <div class="catalog-skeleton-line catalog-skeleton-line--short"></div>
                </div>
            </div>
        @endfor
    </div>

    <div id="catalog-results" class="catalog-results" aria-busy="{{ !empty($showSkeletons) ? 'true' : 'false' }}" aria-live="polite" @if(!empty($showSkeletons)) hidden @endif>
        @include('catalog.partials.results')
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/catalog-loading.js') }}" defer></script>
@endpush
