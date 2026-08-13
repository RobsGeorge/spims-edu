@extends('layouts.app')

@section('title', __('ui.home_title'))

@section('content')
<section class="spims-landing animate-in">
    <div class="spims-landing-panel">
        <p class="spims-landing-brand">{{ __('ui.home_heading') }}</p>
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
</section>
@endsection
