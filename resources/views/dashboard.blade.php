@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
@php
    $user = auth()->user();
@endphp

<div class="animate-in portal-dashboard">
    <div class="mb-4">
        <h1 class="display-6 page-title mb-1">
            {{ __('dashboard.hello', ['name' => $user->first_name ?: __('dashboard.user_fallback')]) }}
        </h1>
        <p class="text-muted-theme mb-0">{{ __('ui.dashboard_subheading') }}</p>
    </div>

    <div class="bento-grid mb-4">
        <section class="bento-courses app-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 spims-title mb-0">{{ __('learning.my_courses') }}</h2>
                @if($featurePublicCatalog ?? true)
                    <a href="{{ route('catalog.index') }}" class="small">{{ __('learning.browse_catalog') }}</a>
                @endif
            </div>
            @if($enrollments->isEmpty())
                <div class="spims-empty py-4 text-center">
                    <p class="text-muted-theme mb-3">{{ __('learning.my_courses_empty') }}</p>
                    @if($featurePublicCatalog ?? true)
                        <a href="{{ route('catalog.index') }}" class="btn btn-primary">{{ __('learning.browse_catalog') }}</a>
                    @endif
                </div>
            @else
                <ul class="list-unstyled mb-0">
                    @foreach($enrollments as $enrollment)
                        <li class="bento-course-row py-2 border-bottom border-opacity-25">
                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                <div>
                                    <div class="fw-semibold">{{ $enrollment->offering->course->code }} · {{ $enrollment->offering->course->title }}</div>
                                    <div class="small text-muted-theme">{{ __('learning.progress', ['percent' => (int) $enrollment->progress_percent]) }}</div>
                                </div>
                                @if($featureLearn ?? true)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('learn.offering', $enrollment->offering) }}">{{ __('learning.open_player') }}</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if($featureLive ?? true)
        <section class="bento-live feature-panel p-3">
            <h2 class="h5 mb-3">{{ __('learning.next_live') }}</h2>
            @if($next_live)
                <p class="mb-1 fw-semibold">{{ $next_live->title }}</p>
                <p class="small mb-3 opacity-75">{{ $next_live->offering->course->code }} · {{ __('learning.starts_at', ['when' => $next_live->scheduled_start->timezone(config('app.timezone'))->format('M j, H:i')]) }}</p>
                <a href="{{ route('live.index') }}" class="btn btn-accent">{{ __('learning.join_session') }}</a>
            @else
                <p class="mb-0 opacity-75">{{ __('learning.next_live_empty') }}</p>
            @endif
        </section>
        @endif

        <section class="bento-due app-card p-3">
            <h2 class="h5 spims-title mb-3">{{ __('learning.due_soon') }}</h2>
            @forelse($due_assessments as $assessment)
                <div class="d-flex justify-content-between gap-2 py-2 border-bottom border-opacity-25">
                    <div>
                        <div class="fw-semibold">{{ $assessment->title }}</div>
                        <div class="small text-muted-theme">
                            {{ $assessment->offering->course->code }}
                            ·
                            @if($assessment->closes_at)
                                {{ __('learning.closes_at', ['when' => $assessment->closes_at->format('M j, H:i')]) }}
                            @else
                                {{ __('learning.no_due_date') }}
                            @endif
                        </div>
                    </div>
                    @if($featureLearn ?? true)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('assessments.show', $assessment) }}">{{ __('learning.continue_learning') }}</a>
                    @endif
                </div>
            @empty
                <p class="text-muted-theme mb-0">{{ __('learning.due_empty') }}</p>
            @endforelse
        </section>

        <section class="bento-wallet app-card p-3">
            <h2 class="h5 spims-title mb-3">{{ __('learning.wallet') }}</h2>
            <div class="row g-2 small">
                <div class="col-6"><div class="wallet-chip">{{ __('learning.egp_money') }}<strong>{{ $wallet['egp_money'] }}</strong></div></div>
                <div class="col-6"><div class="wallet-chip">{{ __('learning.usd_money') }}<strong>{{ $wallet['usd_money'] }}</strong></div></div>
                <div class="col-6"><div class="wallet-chip">{{ __('learning.egp_points') }}<strong>{{ $wallet['egp_points'] }}</strong></div></div>
                <div class="col-6"><div class="wallet-chip">{{ __('learning.usd_points') }}<strong>{{ $wallet['usd_points'] }}</strong></div></div>
            </div>
            <a href="{{ route('finance.index') }}" class="btn btn-sm btn-outline-secondary mt-3">{{ __('dashboard.finance_hub') }}</a>
        </section>

        <section class="bento-notes app-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 spims-title mb-0">{{ __('learning.notifications') }}</h2>
                @if(($unread_notifications ?? 0) > 0)
                    <span class="badge-brand">{{ $unread_notifications }}</span>
                @endif
            </div>
            @forelse($notifications as $note)
                <div class="py-2 border-bottom border-opacity-25">
                    <div class="fw-semibold">{{ $note->title }}</div>
                    <div class="small text-muted-theme">{{ $note->body }}</div>
                </div>
            @empty
                <p class="text-muted-theme mb-0">{{ __('learning.notifications_empty') }}</p>
            @endforelse
            <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary mt-3">{{ __('dashboard.notifications') }}</a>
        </section>
    </div>

    <div class="row g-3">
        @if($featureLearn ?? true)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('hubs.learning') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-book-half" aria-hidden="true"></i> {{ __('dashboard.learning_hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.learning_hub_desc') }}</p>
                </a>
            </div>
        @endif
        @if($hasAcademic)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('hubs.academic') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-mortarboard" aria-hidden="true"></i> {{ __('dashboard.academic_hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.academic_hub_desc') }}</p>
                </a>
            </div>
        @endif
        @if($hasAdmin)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('hubs.admin') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-gear" aria-hidden="true"></i> {{ __('dashboard.admin_hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.admin_hub_desc') }}</p>
                </a>
            </div>
        @endif
        @if(!empty($canManagePeople))
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('admin.users.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-people" aria-hidden="true"></i> {{ __('people.dashboard_tile') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('people.dashboard_tile_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('people.dashboard_tile_hint') }}</p>
                </a>
            </div>
        @endif
        @if($featureLearn ?? true)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('grades.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-clipboard-data" aria-hidden="true"></i> {{ __('learning.grades') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('hubs.grades_desc') }}</p>
                </a>
            </div>
        @endif
        @if($featureEvents ?? true)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('events.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-calendar-event" aria-hidden="true"></i> {{ __('events.hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('events.hub_desc') }}</p>
                </a>
            </div>
        @endif
        @if($featureSurveys ?? true)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('student.surveys.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-clipboard-check" aria-hidden="true"></i> {{ __('dashboard.surveys') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.surveys_desc') }}</p>
                </a>
            </div>
        @endif
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('settings.edit') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-person-gear" aria-hidden="true"></i> {{ __('learning.settings') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('hubs.settings_desc') }}</p>
            </a>
        </div>
        @if($hasSuperadmin)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('superadmin.features') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-toggles" aria-hidden="true"></i> {{ __('features.dashboard_tile') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('features.dashboard_tile_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('features.dashboard_tile_hint') }}</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('superadmin.config') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-sliders2" aria-hidden="true"></i> {{ __('system_settings.dashboard_tile') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('system_settings.dashboard_tile_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('system_settings.dashboard_tile_hint') }}</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('superadmin.theme.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-palette" aria-hidden="true"></i> {{ __('theme_studio.dashboard_tile') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('theme_studio.dashboard_tile_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('theme_studio.dashboard_tile_hint') }}</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('superadmin.reports') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-bar-chart-line" aria-hidden="true"></i> {{ __('school_reports.dashboard_tile') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('school_reports.dashboard_tile_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('school_reports.dashboard_tile_hint') }}</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('superadmin.ops') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-hdd-network" aria-hidden="true"></i> {{ __('ops.dashboard_tile') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('ops.dashboard_tile_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('ops.dashboard_tile_hint') }}</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('superadmin.status') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i> {{ __('status.dashboard_tile') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('status.dashboard_tile_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('status.dashboard_tile_hint') }}</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('superadmin.audit.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-journal-text" aria-hidden="true"></i> {{ __('audit.dashboard_tile') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('audit.dashboard_tile_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('audit.dashboard_tile_hint') }}</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('superadmin.index') }}"
                   class="app-tile hub-tile hub-tile-superadmin d-flex flex-column h-100 text-decoration-none border border-danger border-opacity-25">
                    <h3>
                        @include('partials.superadmin-entry-tag', ['class' => 'me-1'])
                        {{ __('dashboard.superadmin_hub') }}
                    </h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.superadmin_hub_desc') }}</p>
                    <p class="sa-tile-hint small mb-0 mt-2">{{ __('superadmin.dashboard_hint') }}</p>
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
