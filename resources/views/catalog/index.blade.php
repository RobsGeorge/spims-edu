@extends('layouts.app')
@section('title', __('ui.nav_catalog'))
@section('content')
<div class="animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="spims-title mb-1">{{ __('ui.nav_catalog') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('hubs.catalog_desc') }}</p>
        </div>
    </div>

    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    <form method="GET" action="{{ route('catalog.index') }}" class="catalog-filters app-card p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="catalog-q">{{ __('catalog.search_placeholder') }}</label>
                <input id="catalog-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('catalog.search_placeholder') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="catalog-type">{{ __('catalog.programs') }}</label>
                <select id="catalog-type" name="type" class="form-select">
                    <option value="all" @selected($filters['type']==='all')>{{ __('catalog.filter_all') }}</option>
                    <option value="standalone" @selected($filters['type']==='standalone')>{{ __('catalog.filter_standalone') }}</option>
                    <option value="program" @selected($filters['type']==='program')>{{ __('catalog.filter_program') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="catalog-price">{{ __('catalog.filter_paid') }}</label>
                <select id="catalog-price" name="price" class="form-select">
                    <option value="all" @selected($filters['price']==='all')>{{ __('catalog.filter_all') }}</option>
                    <option value="free" @selected($filters['price']==='free')>{{ __('catalog.filter_free') }}</option>
                    <option value="paid" @selected($filters['price']==='paid')>{{ __('catalog.filter_paid') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">{{ __('ui.continue') }}</button>
            </div>
        </div>
    </form>

    @if($courses->isEmpty())
        <div class="spims-empty app-card p-5 text-center">
            <h2 class="h5 spims-title">{{ __('catalog.empty') }}</h2>
            <p class="text-muted-theme mb-0">{{ __('catalog.empty_hint') }}</p>
        </div>
    @else
        <div class="row g-3">
            @foreach($courses as $course)
                @php
                    $offering = $offeringsByCourse->get($course->id)?->first();
                    $form = $course->programCourses
                        ->map(fn ($pc) => $pc->program?->applicationForms?->first())
                        ->filter()
                        ->first();
                @endphp
                <div class="col-md-6 col-xl-4">
                    <article class="catalog-card app-card h-100 p-3 d-flex flex-column">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @if($course->is_free)<span class="badge-brand">{{ __('catalog.free_badge') }}</span>@endif
                            @if($course->is_standalone)<span class="badge-brand">{{ __('catalog.standalone_badge') }}</span>@endif
                        </div>
                        <h2 class="h5 spims-title mb-1">{{ $course->code }}</h2>
                        <p class="mb-2">{{ $course->title }}</p>
                        <p class="text-muted-theme small mb-3">
                            {{ __('catalog.credits', ['count' => $course->credit_hours]) }}
                            · {{ __('catalog.interest_count', ['count' => $course->interest_flags_count]) }}
                        </p>
                        <div class="mt-auto d-flex flex-wrap gap-2">
                            @if($offering)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('offerings.preview', $offering) }}">{{ __('catalog.preview') }}</a>
                            @else
                                <span class="text-muted-theme small align-self-center">{{ __('catalog.no_offering') }}</span>
                            @endif
                            @auth
                                @if($form)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('applications.create', $form) }}">{{ __('catalog.apply') }}</a>
                                @endif
                                <form method="POST" action="{{ route('catalog.interest', $course) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ __('catalog.flag_interest') }}</button>
                                </form>
                            @endauth
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $courses->links() }}</div>
    @endif
</div>
@endsection
