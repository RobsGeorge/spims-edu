@extends('layouts.app')

@section('title', __('status.page_title'))

@section('content')
<div class="sa-status hub-page animate-in" style="max-width:1100px;margin:0 auto;">
    <nav class="mb-3 small" aria-label="{{ __('status.page_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('status.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('status.page_title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('status.page_title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('status.page_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('status.page_help') }}</p>
    </header>

    <aside class="sa-callout sa-callout-danger mb-3" role="note">
        <i class="bi bi-eye-slash-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('status.danger_title') }}</strong>
            <p class="mb-0">{{ __('status.danger_body') }}</p>
        </div>
    </aside>
    <aside class="sa-callout sa-callout-info mb-4" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('status.readonly_title') }}</strong>
            <p class="mb-0">{{ __('status.readonly_body') }}</p>
        </div>
    </aside>

    <p class="small mb-4">
        <a href="#status-health">{{ __('status.health_title') }}</a>
        · <a href="#status-integrations">{{ __('status.integrations_title') }}</a>
        · <a href="#status-runtime">{{ __('status.runtime_title') }}</a>
        · <a href="#status-jobs">{{ __('status.jobs_title') }}</a>
        · <a href="#status-backup">{{ __('status.backup_title') }}</a>
        · <a href="#status-reveals">{{ __('status.reveals_title') }}</a>
        · <a href="#status-demo">{{ __('status.demo_title') }}</a>
    </p>

    <section class="app-card card shadow-sm mb-4" id="status-health" aria-labelledby="status-health-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="status-health-heading">{{ __('status.health_title') }}</h2>
            <p class="text-muted-theme">{{ __('status.health_help') }}</p>
            <p class="mb-3">
                <span class="text-muted-theme">{{ __('status.health_status') }}:</span>
                <span class="sa-flag-state {{ $health['ok'] ? 'is-on' : 'is-off' }}" data-health-status="{{ $health['status'] }}">
                    {{ $health['ok'] ? __('status.health_ok') : __('status.health_degraded') }}
                </span>
            </p>
            <div class="row g-3 mb-3">
                @foreach(['app', 'database', 'cache'] as $check)
                    <div class="col-md-4">
                        <article class="app-card card shadow-sm h-100" data-health-check="{{ $check }}">
                            <div class="card-body">
                                <p class="text-muted-theme small text-uppercase mb-1">{{ __('status.check_'.$check) }}</p>
                                <p class="h4 page-title mb-1">
                                    <span class="sa-flag-state {{ $health['checks'][$check] ? 'is-on' : 'is-off' }}">
                                        {{ $health['checks'][$check] ? __('status.health_pass') : __('status.health_fail') }}
                                    </span>
                                </p>
                                <p class="form-text mb-0">{{ __('status.check_'.$check.'_help') }}</p>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
            <p class="small mb-1">
                <span class="text-muted-theme">{{ __('status.health_timestamp') }}:</span>
                <code>{{ $health['timestamp'] }}</code>
            </p>
            <p class="form-text mb-3">{{ __('status.health_timestamp_help') }}</p>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('health') }}" target="_blank" rel="noopener">
                <i class="bi bi-heart-pulse" aria-hidden="true"></i> {{ __('status.open_health_json') }}
            </a>
            <p class="form-text mb-0 mt-2">{{ __('status.open_health_help') }}</p>
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="status-integrations" aria-labelledby="status-integrations-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="status-integrations-heading">{{ __('status.integrations_title') }}</h2>
            <p class="text-muted-theme">{{ __('status.integrations_help') }}</p>
            <p class="form-text mb-3">{{ __('status.integrations_never') }}</p>
            <ul class="list-unstyled mb-3">
                @foreach($integrations as $integration)
                    <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom border-opacity-25" data-integration="{{ $integration['id'] }}">
                        <span>{{ __('system_settings.integration_'.$integration['id']) }}</span>
                        <span class="sa-integration-pill {{ $integration['configured'] ? 'is-on' : 'is-off' }}">
                            {{ $integration['configured'] ? __('status.configured') : __('status.missing') }}
                        </span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('superadmin.config') }}">{{ __('status.open_config') }}</a>
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="status-runtime" aria-labelledby="status-runtime-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="status-runtime-heading">{{ __('status.runtime_title') }}</h2>
            <p class="text-muted-theme mb-3">{{ __('status.runtime_help') }}</p>
            <dl class="row mb-0">
                @foreach(['queue', 'session', 'cache', 'mailer', 'filesystem', 'timezone'] as $key)
                    <dt class="col-sm-3">{{ __('status.runtime_'.$key) }}</dt>
                    <dd class="col-sm-9">
                        <code>{{ $runtime[$key] }}</code>
                        <span class="form-text d-block">{{ __('status.runtime_'.$key.'_help') }}</span>
                    </dd>
                @endforeach
            </dl>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <section class="app-card card shadow-sm h-100" id="status-jobs" aria-labelledby="status-jobs-heading">
                <div class="card-body">
                    <h2 class="h5 page-title" id="status-jobs-heading">{{ __('status.jobs_title') }}</h2>
                    <p class="text-muted-theme">{{ __('status.jobs_help') }}</p>
                    <p class="mb-1" data-stat="failed_jobs">
                        <span class="text-muted-theme">{{ __('status.jobs_failed') }}:</span>
                        <strong>{{ number_format($jobs['failed_count']) }}</strong>
                    </p>
                    <p class="mb-3">
                        <span class="text-muted-theme">{{ __('status.jobs_pending') }}:</span>
                        <strong>{{ number_format($jobs['pending_count']) }}</strong>
                    </p>
                    <a href="{{ route('superadmin.ops') }}">{{ __('status.open_ops') }}</a>
                    <h3 class="h6 page-title mt-4" id="status-backup">{{ __('status.backup_title') }}</h3>
                    <p class="form-text mb-2">{{ __('status.backup_help') }}</p>
                    <p class="small mb-0">
                        <span class="text-muted-theme">{{ __('ops.last_backup') }}:</span>
                        @if($backup['last_backup_name'])
                            <code>{{ $backup['last_backup_name'] }}</code>
                        @else
                            {{ __('status.backup_none') }}
                        @endif
                    </p>
                    <p class="form-text mb-0">{{ __('status.backup_retention') }}: {{ $backup['retention_days'] }}</p>
                </div>
            </section>
        </div>
        <div class="col-md-6">
            <section class="app-card card shadow-sm h-100" id="status-reveals" aria-labelledby="status-reveals-heading">
                <div class="card-body">
                    <h2 class="h5 page-title" id="status-reveals-heading">{{ __('status.reveals_title') }}</h2>
                    <p class="text-muted-theme">{{ __('status.reveals_help') }}</p>
                    @if($reveals['available'])
                        <p class="mb-1">
                            <span class="text-muted-theme">{{ __('status.reveals_pending') }}:</span>
                            <strong data-stat="reveals_pending">{{ number_format($reveals['pending']) }}</strong>
                        </p>
                        <p class="mb-3">
                            <span class="text-muted-theme">{{ __('status.reveals_total') }}:</span>
                            <strong>{{ number_format($reveals['total']) }}</strong>
                        </p>
                        @if(\Illuminate\Support\Facades\Route::has('superadmin.feedback-reveals.index'))
                            <a href="{{ route('superadmin.feedback-reveals.index') }}">{{ __('status.open_reveals') }}</a>
                        @endif
                    @else
                        <p class="mb-0">{{ __('status.reveals_missing') }}</p>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <section class="app-card card shadow-sm mb-4" id="status-demo" aria-labelledby="status-demo-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="status-demo-heading">{{ __('status.demo_title') }}</h2>
            <p class="text-muted-theme">{{ __('status.demo_help') }}</p>
            @if($demo['routed'])
                <p>{{ __('status.demo_routed') }}</p>
                <a href="{{ $demo['url'] }}">{{ __('status.open_demo') }}</a>
            @else
                <p class="mb-0">{{ __('status.demo_missing') }}</p>
            @endif
        </div>
    </section>

    <p class="small text-muted-theme mb-0">
        <a href="{{ route('superadmin.observability.index') }}">{{ __('status.open_observability') }}</a>
        · <a href="{{ route('superadmin.ops') }}">{{ __('status.open_ops') }}</a>
        · <a href="{{ route('superadmin.config') }}">{{ __('status.open_config') }}</a>
        · <a href="{{ route('superadmin.audit.index') }}">{{ __('superadmin.tile_audit') }}</a>
    </p>
</div>
@endsection
