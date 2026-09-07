@extends('layouts.app')

@section('title', __('ui.home_title'))

@section('content')
<section class="spims-landing animate-in">
    <div class="spims-landing-panel">
        <h1 class="spims-landing-brand">{{ __('ui.home_heading') }}</h1>
        <p class="spims-landing-lead text-muted-theme">{{ __('ui.home_subheading') }}</p>
        <div class="spims-landing-actions">
            @guest
                <a href="{{ route('auth.register') }}" class="btn btn-primary">{{ __('ui.home_cta_primary') }}</a>
                <a href="{{ route('auth.login') }}" class="btn btn-outline-primary">{{ __('ui.home_cta_secondary') }}</a>
            @else
                <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('ui.home_cta_dashboard') }}</a>
                <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary">{{ __('ui.home_cta_catalog') }}</a>
            @endguest
        </div>
    </div>

    <div class="spims-landing-band" aria-labelledby="landing-how-title">
        <h2 id="landing-how-title" class="spims-landing-band-title">{{ __('home.how_title') }}</h2>
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
@endsection
