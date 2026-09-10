@extends('layouts.app')

@section('title', __('ui.home_title'))

@section('content')
<section class="spims-landing animate-in">

    {{-- ===== SECTION 1: HERO ===== --}}
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

    {{-- ===== STATS STRIP ===== --}}
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

    {{-- ===== SECTION 2: PROGRAM TIER STRIP (hidden when no active programs) ===== --}}
    @if($programTiers->isNotEmpty())
        <section id="programs" class="spims-landing-tiers" aria-labelledby="landing-tiers-title">
            <div class="spims-landing-tiers-intro">
                <h2 id="landing-tiers-title" class="spims-landing-band-title">{{ __('home.tier_title') }}</h2>
                <p class="spims-text-dim mb-0">{{ __('home.tier_lead') }}</p>
            </div>
            <div class="spims-landing-tier-strip">
                @foreach($programTiers as $tier)
                    <div class="spims-landing-tier-card">
                        <div class="spims-landing-tier-icon" aria-hidden="true">
                            @if($tier['type'] === \App\Enums\ProgramType::Diploma)
                                &#127891;
                            @elseif($tier['type'] === \App\Enums\ProgramType::Certificate)
                                &#128203;
                            @else
                                &#127979;
                            @endif
                        </div>
                        <div class="spims-landing-tier-name">
                            @if($tier['type'] === \App\Enums\ProgramType::Diploma)
                                {{ __('home.tier_diploma') }}
                            @elseif($tier['type'] === \App\Enums\ProgramType::Certificate)
                                {{ __('home.tier_certificate') }}
                            @else
                                {{ __('home.tier_degree') }}
                            @endif
                        </div>
                        <div class="spims-landing-tier-count">
                            {{ trans_choice('home.tier_programs_count', $tier['count'], ['count' => $tier['count']]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===== SECTION 3: FEATURED COURSES ===== --}}
    @if($featured->isNotEmpty())
        <section class="spims-landing-featured" aria-labelledby="landing-featured-title">
            <div class="spims-landing-featured-intro">
                <h2 id="landing-featured-title" class="spims-landing-featured-title">{{ __('home.featured_title') }}</h2>
                <p class="spims-text-dim mb-0">{{ __('home.featured_lead') }}</p>
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
                            <div class="spims-landing-course-meta">
                                @if($course->is_free)
                                    <span class="spims-landing-course-free">{{ __('home.featured_free_badge') }}</span>
                                @elseif($course->default_price_usd)
                                    <span class="spims-landing-course-price">
                                        <x-money :minor="(int) $course->default_price_usd" :currency="\App\Enums\Currency::Usd" />
                                    </span>
                                @endif
                                <span>{{ __('catalog.credits', ['count' => $course->credit_hours]) }}</span>
                            </div>
                        </a>
                    @else
                        <a class="spims-landing-program" href="{{ $href }}">
                            <div class="spims-landing-program-media {{ $index === 1 ? 'spims-landing-program-media--2' : '' }}" aria-hidden="true"></div>
                            <div class="spims-landing-program-body">
                                <h3 class="spims-landing-program-title">{{ $course->title }}</h3>
                                <p class="spims-landing-program-blurb">
                                    {{ $course->is_standalone ? __('home.featured_standalone_blurb') : __('home.featured_program_blurb') }}
                                </p>
                                <div class="spims-landing-course-meta">
                                    @if($course->is_free)
                                        <span class="spims-landing-course-free">{{ __('home.featured_free_badge') }}</span>
                                    @elseif($course->default_price_usd)
                                        <span class="spims-landing-course-price">
                                            <x-money :minor="(int) $course->default_price_usd" :currency="\App\Enums\Currency::Usd" />
                                        </span>
                                    @endif
                                    <span>{{ __('catalog.credits', ['count' => $course->credit_hours]) }}</span>
                                </div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===== SECTION 4: HOW IT WORKS (4 steps) ===== --}}
    <div id="admissions" class="spims-landing-band" aria-labelledby="landing-how-title">
        <h2 id="landing-how-title" class="spims-landing-band-title">{{ __('home.how_title') }}</h2>
        <p class="spims-text-dim mb-0">{{ __('home.how_lead') }}</p>
        <ol class="spims-landing-beats spims-landing-beats--4">
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
            <li>
                <span class="spims-landing-beat-num" aria-hidden="true">4</span>
                <p class="spims-landing-beat-title">{{ __('home.how_4_title') }}</p>
                <p class="spims-landing-beat-body">{{ __('home.how_4_body') }}</p>
            </li>
        </ol>
        <p class="spims-landing-teaser">
            <a href="{{ route('catalog.index') }}">{{ __('home.catalog_teaser') }}</a>
        </p>
    </div>

    {{-- ===== SECTION 5: FEES & PAYMENTS ===== --}}
    <div id="fees" class="spims-landing-band spims-landing-band--alt" aria-labelledby="landing-fees-title">
        <h2 id="landing-fees-title" class="spims-landing-band-title">{{ __('home.fees_title') }}</h2>
        <p class="spims-text-dim mb-0">{{ __('home.fees_lead') }}</p>
        <div class="spims-landing-fees">
            <div class="spims-landing-fee-item">
                <div class="spims-landing-fee-item-title">{{ __('home.fees_item_1_title') }}</div>
                <div class="spims-landing-fee-item-body">{{ __('home.fees_item_1_body') }}</div>
            </div>
            <div class="spims-landing-fee-item">
                <div class="spims-landing-fee-item-title">{{ __('home.fees_item_2_title') }}</div>
                <div class="spims-landing-fee-item-body">{{ __('home.fees_item_2_body') }}</div>
            </div>
            <div class="spims-landing-fee-item">
                <div class="spims-landing-fee-item-title">{{ __('home.fees_item_3_title') }}</div>
                <div class="spims-landing-fee-item-body">{{ __('home.fees_item_3_body') }}</div>
            </div>
        </div>
    </div>

    {{-- ===== SECTION 6: TRUST STRIP + CREDENTIAL VERIFIER ===== --}}
    <div id="trust" class="spims-landing-trust">
        <div class="spims-landing-trust-inner">
            <div class="spims-landing-trust-copy">
                <h2 class="spims-landing-trust-title">{{ __('home.trust_title') }}</h2>
                <p class="spims-landing-trust-lead">{{ __('home.trust_lead') }}</p>
            </div>

            <div class="spims-landing-verifier"
                 x-data="{
                     serial: '',
                     status: null,
                     loading: false,
                     async verify() {
                         if (! this.serial.trim()) return;
                         this.loading = true;
                         this.status = null;
                         try {
                             const url = {{ Js::from(route('api.verify-serial')) }} + '?serial=' + encodeURIComponent(this.serial.trim());
                             const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                             const data = await res.json();
                             if (! data.found) {
                                 this.status = 'not-found';
                             } else if (data.valid) {
                                 this.status = 'valid';
                             } else {
                                 this.status = 'revoked';
                             }
                         } catch {
                             this.status = 'not-found';
                         } finally {
                             this.loading = false;
                         }
                     }
                 }"
            >
                <h3 class="spims-landing-verifier-title">{{ __('home.verify_title') }}</h3>
                <p class="spims-landing-verifier-lead">{{ __('home.verify_lead') }}</p>
                <form @submit.prevent="verify()" class="spims-landing-verifier-form" novalidate>
                    <div class="input-group">
                        <input
                            type="text"
                            class="form-control"
                            x-model="serial"
                            placeholder="{{ __('home.verify_serial_placeholder') }}"
                            aria-label="{{ __('home.verify_serial_placeholder') }}"
                            :disabled="loading"
                        >
                        <button type="submit" class="btn btn-primary" :disabled="loading || ! serial.trim()">
                            <span x-show="! loading">{{ __('home.verify_btn') }}</span>
                            <span x-show="loading" aria-live="polite">{{ __('home.verify_checking') }}</span>
                        </button>
                    </div>
                </form>
                <div class="spims-landing-verifier-result"
                     x-show="status !== null"
                     :class="{
                         'spims-landing-verifier-result--valid':     status === 'valid',
                         'spims-landing-verifier-result--revoked':   status === 'revoked',
                         'spims-landing-verifier-result--not-found': status === 'not-found'
                     }"
                     role="alert"
                     aria-live="polite"
                >
                    <span x-show="status === 'valid'">{{ __('home.verify_found_valid') }}</span>
                    <span x-show="status === 'revoked'">{{ __('home.verify_found_revoked') }}</span>
                    <span x-show="status === 'not-found'">{{ __('home.verify_not_found') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== SECTION 7: FOOTER CTA ===== --}}
    <div class="spims-landing-cta-band" aria-labelledby="landing-cta-title">
        <h2 id="landing-cta-title" class="spims-landing-cta-title">{{ __('home.footer_cta_title') }}</h2>
        <p class="spims-landing-cta-lead">{{ __('home.footer_cta_lead') }}</p>
        <div class="spims-landing-cta-actions">
            @guest
                <a href="{{ route('auth.register') }}" class="btn btn-primary btn-lg">{{ __('home.footer_cta_register') }}</a>
            @endguest
            @if(Route::has('programs.catalog.index'))
                <a href="{{ route('programs.catalog.index') }}" class="btn btn-outline-primary btn-lg">{{ __('home.footer_cta_browse') }}</a>
            @else
                <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary btn-lg">{{ __('home.footer_cta_browse') }}</a>
            @endif
        </div>
    </div>

</section>

<footer id="spiritual" class="spims-landing-footer">
    <div class="spims-landing-footer-brand">{{ __('ui.home_heading') }}</div>
    <div class="d-flex flex-wrap justify-content-center gap-3">
        <a href="{{ route('catalog.index') }}">{{ __('ui.nav_catalog') }}</a>
        @if(app(\App\Services\SystemDocs\SystemDocsCatalog::class)->canBrowse(auth()->user()))
            <a href="{{ route('system-docs.index') }}">{{ __('system_docs.nav') }}</a>
        @endif
        <a href="{{ route('auth.register') }}">{{ __('ui.register') }}</a>
    </div>
    <div>{{ __('home.footer_copy') }}</div>
</footer>
@endsection
