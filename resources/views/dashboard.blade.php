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
                <a href="{{ route('catalog.index') }}" class="small">{{ __('learning.browse_catalog') }}</a>
            </div>
            @if($enrollments->isEmpty())
                <div class="spims-empty py-4 text-center">
                    <p class="text-muted-theme mb-3">{{ __('learning.my_courses_empty') }}</p>
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary">{{ __('learning.browse_catalog') }}</a>
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
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('courses.player', $enrollment->offering) }}">{{ __('learning.open_player') }}</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

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
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('assessments.show', $assessment) }}">{{ __('learning.continue_learning') }}</a>
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
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('hubs.learning') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-book-half" aria-hidden="true"></i> {{ __('dashboard.learning_hub') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('dashboard.learning_hub_desc') }}</p>
            </a>
        </div>
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
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('grades.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-clipboard-data" aria-hidden="true"></i> {{ __('learning.grades') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('hubs.grades_desc') }}</p>
            </a>
        </div>
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('settings.edit') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-person-gear" aria-hidden="true"></i> {{ __('learning.settings') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('hubs.settings_desc') }}</p>
            </a>
        </div>
        @if($hasSuperadmin)
            <div class="col-md-6 col-lg-4">
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
