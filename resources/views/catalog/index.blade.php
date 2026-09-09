@extends('layouts.app')
@section('title', __('ui.nav_catalog'))

@section('content')
<div class="animate-in">

    <x-page-header
        :title="__('ui.nav_catalog')"
        :subtitle="__('hubs.catalog_desc')"
    />

    @if(session('status'))
        <div class="alert alert-success academic-alert">{{ session('status') }}</div>
    @endif

    {{-- ── Featured standalone courses ────────────────────────────────── --}}
    @if($standaloneCourses->isNotEmpty() && empty($hasActiveFilters))
        <section class="catalog-featured mb-5" aria-label="{{ __('catalog.featured_chip') }}">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="spims-badge spims-badge--info">{{ __('catalog.featured_chip') }}</span>
            </div>
            <div class="row g-3">
                @foreach($standaloneCourses->take(3) as $index => $course)
                    @php $offering = $standaloneOfferingsByCourse->get($course->id)?->first(); @endphp
                    @if($offering)
                        <div class="col-12 col-md-6 col-xl-4">
                            @include('partials.course-card', [
                                'course'   => $course,
                                'offering' => $offering,
                                'index'    => $index,
                            ])
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{--
        The x-tabs component creates an Alpine x-data scope.
        Placing the filter form inside the slot allows x-bind:value="tab"
        to keep the hidden "tab" input in sync without a page reload.
    --}}
    <x-tabs
        id="catalog-tabs"
        :items="[
            ['key' => 'programs',   'label' => __('catalog.tab_programs'),   'icon' => 'course'],
            ['key' => 'courses',    'label' => __('catalog.tab_courses'),    'icon' => 'course'],
            ['key' => 'standalone', 'label' => __('catalog.tab_standalone'), 'icon' => 'course'],
        ]"
        x-init="tab = @js($filters['tab'])"
    >

        {{-- ── Shared filter form ─────────────────────────────────────────── --}}
        <form method="GET"
              action="{{ route('catalog.index') }}"
              class="catalog-filters academic-form spims-filter-bar mb-4"
              aria-controls="catalog-results-courses catalog-results-standalone">

            <x-card variant="quiet" class="p-3 w-100">
                {{-- Hidden tab field — kept in sync by Alpine --}}
                <input type="hidden" name="tab" x-bind:value="tab">

                <div class="row g-2 align-items-end">
                    {{-- Search --}}
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label spims-field-label" for="catalog-q">
                            {{ __('catalog.search_placeholder') }}
                        </label>
                        <input
                            id="catalog-q"
                            type="search"
                            name="q"
                            value="{{ $filters['q'] }}"
                            class="form-control"
                            placeholder="{{ __('catalog.search_placeholder') }}"
                        >
                    </div>

                    {{-- Program type filter — shown on Programs + Courses tabs --}}
                    <div class="col-6 col-md-4 col-lg-2" x-show="tab !== 'standalone'" x-cloak>
                        <label class="form-label spims-field-label" for="catalog-type">
                            {{ __('catalog.filter_program_type') }}
                        </label>
                        <select id="catalog-type" name="type" class="form-select">
                            <option value="all" @selected($filters['type'] === 'all')>
                                {{ __('catalog.filter_all_types') }}
                            </option>
                            <option value="diploma" @selected($filters['type'] === 'diploma')>
                                {{ __('program_type.DIPLOMA') }}
                            </option>
                            <option value="certificate" @selected($filters['type'] === 'certificate')>
                                {{ __('program_type.CERTIFICATE') }}
                            </option>
                            <option value="degree" @selected($filters['type'] === 'degree')>
                                {{ __('program_type.DEGREE') }}
                            </option>
                        </select>
                    </div>

                    {{-- Delivery mode filter — shown on Courses + Standalone tabs --}}
                    <div class="col-6 col-md-4 col-lg-2" x-show="tab !== 'programs'" x-cloak>
                        <label class="form-label spims-field-label" for="catalog-mode">
                            {{ __('catalog.filter_mode') }}
                        </label>
                        <select id="catalog-mode" name="mode" class="form-select">
                            <option value="all" @selected($filters['mode'] === 'all')>
                                {{ __('catalog.filter_all_modes') }}
                            </option>
                            <option value="cohort" @selected($filters['mode'] === 'cohort')>
                                {{ __('catalog.filter_mode_cohort') }}
                            </option>
                            <option value="self_paced" @selected($filters['mode'] === 'self_paced')>
                                {{ __('catalog.filter_mode_self_paced') }}
                            </option>
                        </select>
                    </div>

                    {{-- Price filter — Courses + Standalone only --}}
                    <div class="col-6 col-md-4 col-lg-2" x-show="tab !== 'programs'" x-cloak>
                        <label class="form-label spims-field-label" for="catalog-price">
                            {{ __('catalog.filter_paid') }}
                        </label>
                        <select id="catalog-price" name="price" class="form-select">
                            <option value="all"  @selected($filters['price'] === 'all')>{{ __('catalog.filter_all') }}</option>
                            <option value="free" @selected($filters['price'] === 'free')>{{ __('catalog.filter_free') }}</option>
                            <option value="paid" @selected($filters['price'] === 'paid')>{{ __('catalog.filter_paid') }}</option>
                        </select>
                    </div>

                    {{-- Sort — Courses + Standalone only --}}
                    <div class="col-6 col-md-4 col-lg-2" x-show="tab !== 'programs'" x-cloak>
                        <label class="form-label spims-field-label" for="catalog-sort">
                            {{ __('catalog.sort') }}
                        </label>
                        <select id="catalog-sort" name="sort" class="form-select">
                            <option value="code"     @selected($filters['sort'] === 'code')>{{ __('catalog.sort_code') }}</option>
                            <option value="interest" @selected($filters['sort'] === 'interest')>{{ __('catalog.sort_interest') }}</option>
                        </select>
                    </div>

                    @auth
                        {{-- Interest filter — Courses only --}}
                        <div class="col-6 col-md-4 col-lg-2" x-show="tab === 'courses'" x-cloak>
                            <label class="form-label spims-field-label" for="catalog-interest">
                                {{ __('catalog.interest_filter') }}
                            </label>
                            <select id="catalog-interest" name="interest" class="form-select">
                                <option value="all"     @selected($filters['interest'] === 'all')>{{ __('catalog.interest_all') }}</option>
                                <option value="flagged" @selected($filters['interest'] === 'flagged')>{{ __('catalog.interest_flagged') }}</option>
                            </select>
                        </div>
                    @endauth

                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-primary w-100">{{ __('ui.continue') }}</button>
                    </div>
                </div>
            </x-card>
        </form>

        {{-- ══════════════════════════════════════════════════════════════════
             Tab Panel: Programs
        ══════════════════════════════════════════════════════════════════════ --}}
        <div
            id="catalog-tabs-panel-programs"
            role="tabpanel"
            aria-labelledby="catalog-tabs-tab-programs"
            x-show="tab === 'programs'"
            x-cloak
        >
            @if($programs->isEmpty())
                <x-empty-state
                    :title="__('catalog.no_programs')"
                    :message="__('catalog.no_programs_hint')"
                    icon="bi-journal-text"
                >
                    <x-slot:actions>
                        <a href="{{ route('help.show', 'browse-catalog-enroll') }}" class="btn btn-outline-secondary btn-sm">{{ __('help.catalog_enroll_cta') }}</a>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <div class="grid-auto-md">
                    @foreach($programs as $program)
                        @include('partials.program-card', ['program' => $program])
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             Tab Panel: Courses
        ══════════════════════════════════════════════════════════════════════ --}}
        <div
            id="catalog-results-courses"
            role="tabpanel"
            aria-labelledby="catalog-tabs-tab-courses"
            aria-busy="{{ !empty($showSkeletons) ? 'true' : 'false' }}"
            aria-live="polite"
            data-catalog-loading="{{ route('catalog.index') }}"
            x-show="tab === 'courses'"
            x-cloak
        >
            {{-- Skeleton loading state --}}
            <div class="catalog-skeletons">
                @if(!empty($showSkeletons))
                    <p class="spims-text-dim mb-3">{{ __('catalog.loading') }}</p>
                    <div class="row g-3">
                        @for($i = 0; $i < 6; $i++)
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="catalog-skeleton-card spims-skeleton" aria-hidden="true">
                                    <div class="catalog-card-media" aria-hidden="true"></div>
                                    <div class="spims-skeleton-line mb-2" style="width: 40%"></div>
                                    <div class="spims-skeleton-line mb-1" style="width: 70%"></div>
                                    <div class="spims-skeleton-line" style="width: 55%"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                @endif
            </div>

            @if(empty($showSkeletons))
                @include('catalog.partials.results', [
                    'courses'          => $courses,
                    'offeringsByCourse'=> $offeringsByCourse,
                    'emptyTitle'       => __('catalog.empty'),
                    'emptyHint'        => __('catalog.empty_hint'),
                ])
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             Tab Panel: Standalone
        ══════════════════════════════════════════════════════════════════════ --}}
        <div
            id="catalog-results-standalone"
            role="tabpanel"
            aria-labelledby="catalog-tabs-tab-standalone"
            x-show="tab === 'standalone'"
            x-cloak
        >
            @if($standaloneCourses->isEmpty())
                <x-empty-state
                    :title="__('catalog.no_standalone')"
                    :message="__('catalog.no_standalone_hint')"
                    icon="bi-journal-text"
                >
                    <x-slot:actions>
                        <a href="{{ route('help.show', 'browse-catalog-enroll') }}" class="btn btn-outline-secondary btn-sm">{{ __('help.catalog_enroll_cta') }}</a>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <div class="row g-3">
                    @foreach($standaloneCourses as $index => $course)
                        @php
                            $offering = $standaloneOfferingsByCourse->get($course->id)?->first();
                        @endphp
                        <div class="col-12 col-md-6 col-xl-4">
                            @include('partials.course-card', [
                                'course'   => $course,
                                'offering' => $offering,
                                'index'    => $index,
                            ])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </x-tabs>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/catalog-loading.js') }}" defer></script>
@endpush

@push('styles')
<style>
/* ── Catalog card shared styles ─────────────────────────────────── */
.catalog-card-title {
    font-size: var(--text-lg);
    line-height: var(--leading-lg);
}
.catalog-card-desc {
    font-size: var(--text-sm);
    line-height: var(--leading-base);
    color: var(--color-text-muted);
}
.catalog-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    margin: 0;
    padding: 0;
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    list-style: none;
}
.catalog-card-meta-item {
    display: flex;
    align-items: center;
    gap: var(--space-1);
}
.catalog-card-price {
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--color-title);
}
.catalog-part-of-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-1);
}
.catalog-chip {
    display: inline-flex;
    align-items: center;
    padding-inline: var(--space-2);
    min-height: 1.5rem;
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    font-weight: 600;
    color: var(--color-primary);
    background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface));
    border: 1px solid color-mix(in srgb, var(--color-primary) 25%, transparent);
    transition: background var(--motion-fast);
}
.catalog-chip:hover,
.catalog-chip:focus {
    background: color-mix(in srgb, var(--color-primary) 18%, var(--color-surface));
}

/* ── Program card hover ─────────────────────────────────────────── */
.program-catalog-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform var(--motion-base), box-shadow var(--motion-base);
}
.program-catalog-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-floating);
}
.program-catalog-card-link { display: block; }

/* ── Course card ────────────────────────────────────────────────── */
.catalog-course-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform var(--motion-base), box-shadow var(--motion-base);
}
.catalog-course-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-floating);
}

/* ── Decorative media band ──────────────────────────────────────── */
.catalog-card-media {
    height: 4px;
    border-radius: var(--radius-full);
    margin-bottom: var(--space-3);
    background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-accent) 100%);
}
.catalog-card-media--alt {
    background: linear-gradient(90deg, var(--color-accent) 0%, var(--color-primary) 100%);
}

@media (prefers-reduced-motion: reduce) {
    .program-catalog-card,
    .catalog-course-card {
        transition: none !important;
    }
    .program-catalog-card:hover,
    .catalog-course-card:hover {
        transform: none !important;
    }
}
</style>
@endpush
