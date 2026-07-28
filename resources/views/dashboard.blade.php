@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
@php
    use App\Support\NavigationHub;
    $user = auth()->user();
    $hasAcademic = count(NavigationHub::academicLinks($user)) > 0;
    $hasAdmin = count(NavigationHub::adminLinks($user)) > 0;
    $hasFinanceAdmin = NavigationHub::hasFinanceAdmin($user);
    $hasSuperadmin = NavigationHub::hasSuperadmin($user);
@endphp

<div class="animate-in portal-dashboard" style="max-width: 920px; margin: 0 auto;">
    <div class="text-center my-4 my-md-5">
        <p class="display-6 fw-bold page-title mb-0">
            {{ __('dashboard.hello', ['name' => $user->first_name ?: __('dashboard.user_fallback')]) }}
        </p>
        <p class="text-muted-theme mt-2 mb-0">{{ __('ui.dashboard_subheading') }}</p>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <a href="{{ route('hubs.learning') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-book-half" aria-hidden="true"></i> {{ __('dashboard.learning_hub') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('dashboard.learning_hub_desc') }}</p>
            </a>
        </div>

        @if($hasAcademic)
            <div class="col-md-6">
                <a href="{{ route('hubs.academic') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-mortarboard" aria-hidden="true"></i> {{ __('dashboard.academic_hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.academic_hub_desc') }}</p>
                </a>
            </div>
        @endif

        @if($hasAdmin)
            <div class="col-md-6">
                <a href="{{ route('hubs.admin') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-gear" aria-hidden="true"></i> {{ __('dashboard.admin_hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.admin_hub_desc') }}</p>
                </a>
            </div>
        @endif

        <div class="col-md-6">
            <a href="{{ route('hubs.finance') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-wallet2" aria-hidden="true"></i> {{ __('dashboard.finance_hub') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('dashboard.finance_hub_desc') }}</p>
            </a>
        </div>

        <div class="col-md-6">
            <a href="{{ route('notifications.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-bell" aria-hidden="true"></i> {{ __('dashboard.notifications') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('dashboard.notifications_desc') }}</p>
            </a>
        </div>

        @if($hasSuperadmin)
            <div class="col-md-6">
                <a href="{{ route('superadmin.index') }}"
                   class="app-tile hub-tile hub-tile-superadmin d-flex flex-column h-100 text-decoration-none border border-danger border-opacity-25">
                    <h3>
                        @include('partials.superadmin-entry-tag', ['class' => 'me-1'])
                        {{ __('dashboard.superadmin_hub') }}
                    </h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.superadmin_hub_desc') }}</p>
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
