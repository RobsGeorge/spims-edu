@extends('layouts.app')

@section('title', __('ui.home_title'))

@section('content')
<section class="spims-landing animate-in">
    <div class="spims-landing-hero">
        <img class="spims-landing-atmosphere spims-landing-atmosphere--photo" src="{{ asset('img/landing-hero.jpg') }}" alt="" decoding="async">
        <div class="spims-landing-hero-inner">
            <p class="spims-landing-chip">{{ __('home.hero_chip') }}</p>
            <p class="spims-landing-heading">{{ __('ui.home_heading') }}</p>
            <h1 class="spims-landing-display">{{ __('home.hero_display') }}</h1>
            <p class="spims-landing-lead">{{ __('ui.home_subheading') }}</p>
            <div class="spims-landing-actions">
                @guest
                    <a href="{{ route('auth.register') }}" class="btn btn-primary">{{ __('ui.home_cta_primary') }}</a>
                    <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary">{{ __('ui.home_cta_catalog') }}</a>
                    <a href="{{ route('auth.login') }}" class="btn btn-outline-primary">{{ __('ui.home_cta_secondary') }}</a>
                @else
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('ui.home_cta_dashboard') }}</a>
                    <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary">{{ __('ui.home_cta_catalog') }}</a>
                @endguest
            </div>
        </div>
    </div>

    <div id="academics" class="spims-landing-stats">
        <div class="spims-landing-stat">
            <div class="spims-landing-stat-value">{{ number_format($stats['students']) }}</div>
            <div class="spims-landing-stat-label">{{ __('home.stat_students') }}</div>
        </div>
        <div class="spims-landing-stat">
            <div class="spims-landing-stat-value">{{ number_format($stats['courses']) }}</div>
            <div class="spims-landing-stat-label">{{ __('home.stat_courses') }}</div>
        </div>
        <div class="spims-landing-stat">
            <div class="spims-landing-stat-value">{{ __('home.stat_faculty_value') }}</div>
            <div class="spims-landing-stat-label">{{ __('home.stat_faculty') }}</div>
        </div>
    </div>

    @if($featured->isNotEmpty())
        <section id="programs" class="spims-landing-featured" aria-labelledby="landing-featured-title">
            <div class="spims-landing-featured-intro">
                <h2 id="landing-featured-title" class="spims-landing-featured-title">{{ __('home.featured_title') }}</h2>
                <p class="text-muted-theme mb-0">{{ __('home.featured_lead') }}</p>
            </div>
            <div class="spims-landing-featured-grid">
                @foreach($featured as $index => $course)
                    @php
                        $href = route('catalog.index', ['q' => $course->code]);
                    @endphp
                    @if($index === 2)
                        <a class="spims-landing-program spims-landing-program--feature" href="{{ $href }}">
                            <div>
                                <p class="spims-landing-chip mb-3">{{ __('home.featured_tile_badge') }}</p>
                                <h3 class="spims-landing-program-title">{{ $course->title }}</h3>
                                <p class="spims-landing-program-blurb">
                                    {{ $course->is_standalone ? __('home.featured_standalone_blurb') : __('home.featured_program_blurb') }}
                                </p>
                            </div>
                            <p class="spims-landing-program-meta mb-0">{{ __('catalog.credits', ['count' => $course->credit_hours]) }}</p>
                        </a>
                    @else
                        <a class="spims-landing-program" href="{{ $href }}">
                            <div class="spims-landing-program-media {{ $index === 1 ? 'spims-landing-program-media--2' : '' }}" aria-hidden="true"></div>
                            <div class="spims-landing-program-body">
                                <h3 class="spims-landing-program-title">{{ $course->title }}</h3>
                                <p class="spims-landing-program-blurb">
                                    {{ $course->is_standalone ? __('home.featured_standalone_blurb') : __('home.featured_program_blurb') }}
                                </p>
                                <p class="spims-landing-program-meta mb-0">{{ __('catalog.credits', ['count' => $course->credit_hours]) }}</p>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <div id="admissions" class="spims-landing-band" aria-labelledby="landing-how-title">
        <h2 id="landing-how-title" class="spims-landing-band-title">{{ __('home.how_title') }}</h2>
        <p class="text-muted-theme mb-0">{{ __('home.how_lead') }}</p>
        <ol class="spims-landing-beats">
            <li>
                <span class="spims-landing-beat-num" aria-hidden="true">1</span>
                <p class="spims-landing-beat-title">{{ __('home.how_1_title') }}</p>
                <p class="spims-landing-beat-body">{{ __('home.how_1_body') }}</p>
            </li>
            <li>
                <span class="spims-landing-beat-num" aria-hidden="true">2</span>
                <p class="spims-landing-beat-title">{{ __('home.how_2_title') }}</p>
                <p class="spims-landing-beat-body">{{ __('home.how_2_body') }}</p>
            </li>
            <li>
                <span class="spims-landing-beat-num" aria-hidden="true">3</span>
                <p class="spims-landing-beat-title">{{ __('home.how_3_title') }}</p>
                <p class="spims-landing-beat-body">{{ __('home.how_3_body') }}</p>
            </li>
        </ol>
        <p class="spims-landing-teaser">
            <a href="{{ route('catalog.index') }}">{{ __('home.catalog_teaser') }}</a>
        </p>
    </div>
</section>

<footer id="spiritual" class="spims-landing-footer">
    <div class="spims-landing-footer-brand">{{ __('ui.home_heading') }}</div>
    <div class="d-flex flex-wrap justify-content-center gap-3">
        <a href="{{ route('catalog.index') }}">{{ __('ui.nav_catalog') }}</a>
        <a href="{{ route('auth.register') }}">{{ __('ui.register') }}</a>
    </div>
    <div>{{ __('home.footer_copy') }}</div>
</footer>
@endsection
