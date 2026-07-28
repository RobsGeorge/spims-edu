@extends('layouts.app')

@section('title', __('ui.home_title'))

@section('content')
    <div class="spims-hero app-card card border-0 shadow-sm mb-4">
        <div class="card-body p-4 p-md-5 text-center text-md-start">
            <h1 class="spims-title mb-3">{{ __('ui.home_heading') }}</h1>
            <p class="text-muted-theme mb-4">{{ __('ui.home_subheading') }}</p>
            @guest
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                    <a href="{{ route('auth.login') }}" class="btn btn-primary">{{ __('ui.login') }}</a>
                    <a href="{{ route('auth.register') }}" class="btn btn-outline-secondary">{{ __('ui.register') }}</a>
                </div>
            @else
                <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('ui.nav_dashboard') }}</a>
            @endguest
        </div>
    </div>

    @auth
        <div class="row g-3">
            <div class="col-md-4">
                <a href="{{ route('dashboard') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-grid"></i> {{ __('ui.nav_dashboard') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('ui.dashboard_subheading') }}</p>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('catalog.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-journal-bookmark"></i> {{ __('ui.nav_catalog') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('hubs.catalog_desc') }}</p>
                </a>
            </div>
            @if(\App\Support\NavigationHub::hasSuperadmin(auth()->user()))
                <div class="col-md-4">
                    <a href="{{ route('superadmin.index') }}" class="app-tile hub-tile hub-tile-superadmin d-flex flex-column h-100 text-decoration-none border border-danger border-opacity-25">
                        <h3>
                            @include('partials.superadmin-entry-tag', ['class' => 'me-1'])
                            {{ __('dashboard.superadmin_hub') }}
                        </h3>
                        <p class="text-muted-theme mb-0">{{ __('dashboard.superadmin_hub_desc') }}</p>
                    </a>
                </div>
            @endif
        </div>
    @endauth
@endsection
