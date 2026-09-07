@extends('layouts.app')

@section('title', __('ops.page_title'))

@section('content')
@php
    $jobs = $failed['jobs'] ?? [];
    $failedCount = (int) ($failed['failed_count'] ?? 0);
    $pendingCount = (int) ($failed['pending_count'] ?? 0);
    $truncated = (bool) ($failed['truncated'] ?? false);
    $sessionDriverKey = 'ops.session_driver_'.$session['driver'];
@endphp
<div class="sa-ops hub-page animate-in" style="max-width:1100px;margin:0 auto;">
    <nav class="mb-3 small" aria-label="{{ __('ops.page_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('ops.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('ops.page_title') }}</span>
    </nav>

    <header class="mb-3">
        <h1 class="page-title mb-2">{{ __('ops.page_title') }}</h1>
        <p class="text-muted-theme mb-2">{{ __('ops.page_lead') }}</p>
        <p class="small text-muted-theme mb-0">{{ __('ops.page_help') }}</p>
    </header>

    <aside class="sa-callout sa-callout-danger mb-3" role="note">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('ops.danger_title') }}</strong>
            <p class="mb-0">{{ __('ops.danger_body') }}</p>
        </div>
    </aside>
    <aside class="sa-callout sa-callout-info mb-4" role="note">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        <div>
            <strong>{{ __('ops.allowlist_title') }}</strong>
            <p class="mb-0">{{ __('ops.allowlist_body') }}</p>
        </div>
    </aside>

    <p class="small mb-4">
        <a href="#ops-jobs">{{ __('ops.jobs_title') }}</a>
        · <a href="#ops-backup">{{ __('ops.backup_title') }}</a>
        · <a href="#ops-schedule">{{ __('ops.schedule_title') }}</a>
        · <a href="#ops-sessions">{{ __('ops.session_title') }}</a>
    </p>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <article class="app-card card shadow-sm h-100" data-stat="failed_jobs">
                <div class="card-body">
                    <p class="text-muted-theme small text-uppercase mb-1">{{ __('ops.jobs_failed_count') }}</p>
                    <p class="display-6 fw-bold page-title mb-1">{{ number_format($failedCount) }}</p>
                    <p class="form-text mb-0">{{ __('ops.jobs_help') }}</p>
                </div>
            </article>
        </div>
        <div class="col-md-4">
            <article class="app-card card shadow-sm h-100" data-stat="jobs">
                <div class="card-body">
                    <p class="text-muted-theme small text-uppercase mb-1">{{ __('ops.jobs_pending') }}</p>
                    <p class="display-6 fw-bold page-title mb-1">{{ number_format($pendingCount) }}</p>
                    <p class="form-text mb-0">{{ __('ops.jobs_pending_help') }}</p>
                </div>
            </article>
        </div>
        <div class="col-md-4">
            <article class="app-card card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted-theme small text-uppercase mb-1">{{ __('ops.session_driver') }}</p>
                    <p class="h4 page-title mb-1"><code>{{ $session['driver'] }}</code></p>
                    <p class="form-text mb-2">{{ __('superadmin.queue_connection') }}: <code>{{ $queueConnection }}</code></p>
                    <p class="form-text mb-0">{{ __('ops.queue_connection_help') }}</p>
                </div>
            </article>
        </div>
    </div>

    <section class="app-card card shadow-sm mb-4" id="ops-jobs" aria-labelledby="ops-jobs-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="ops-jobs-heading">{{ __('ops.jobs_title') }}</h2>
            <p class="text-muted-theme">{{ __('ops.jobs_help') }}</p>
            <p class="form-text">{{ __('ops.jobs_payload_hidden') }}</p>
            <p class="form-text">{{ __('ops.retry_help') }}</p>
            <p class="form-text mb-3">{{ __('ops.delete_help') }}</p>
            @if($truncated)
                <aside class="sa-callout sa-callout-info mb-3" role="status">
                    <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                    <div>
                        <p class="mb-0">{{ __('ops.jobs_truncated', ['count' => $failedCount]) }}</p>
                    </div>
                </aside>
            @endif

            @if($jobs === [])
                <p class="mb-0">{{ __('ops.jobs_empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="visually-hidden">{{ __('ops.jobs_title') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('ops.col_failed_at') }}</th>
                                <th scope="col">{{ __('ops.col_job') }}</th>
                                <th scope="col">{{ __('ops.col_queue') }}</th>
                                <th scope="col">{{ __('ops.col_attempts') }}</th>
                                <th scope="col">{{ __('ops.col_exception') }}</th>
                                <th scope="col">{{ __('ops.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jobs as $job)
                                <tr data-failed-job="{{ $job['uuid'] }}">
                                    <td class="text-nowrap small">{{ $job['failed_at'] ?? '—' }}</td>
                                    <td>
                                        <code>{{ $job['name'] }}</code>
                                        <div class="small text-muted-theme font-monospace">{{ $job['uuid'] }}</div>
                                    </td>
                                    <td>
                                        <span class="small">{{ $job['queue'] }}</span>
                                        <div class="small text-muted-theme">{{ __('ops.col_connection') }}: {{ $job['connection'] }}</div>
                                    </td>
                                    <td>{{ $job['attempts'] }}</td>
                                    <td class="small"><code>{{ $job['exception'] }}</code></td>
                                    <td class="text-nowrap">
                                        <form method="POST" action="{{ route('superadmin.ops.jobs.retry', $job['uuid']) }}" class="d-inline"
                                              onsubmit="return confirm(@json(__('ops.retry_confirm')));">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('ops.retry') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('superadmin.ops.jobs.delete', $job['uuid']) }}" class="d-inline"
                                              onsubmit="return confirm(@json(__('ops.delete_confirm')));">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('ops.delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="ops-backup" aria-labelledby="ops-backup-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="ops-backup-heading">{{ __('ops.backup_title') }}</h2>
            <p class="text-muted-theme">{{ __('ops.backup_help') }}</p>
            <p class="form-text">{{ __('ops.backup_path_help') }}</p>
            <p class="form-text mb-3">{{ __('ops.last_backup_help') }}</p>
            <dl class="row mb-3">
                <dt class="col-sm-3">{{ __('ops.backup_path') }}</dt>
                <dd class="col-sm-9"><code>{{ $backup['path'] }}</code></dd>
                <dt class="col-sm-3">{{ __('ops.backup_retention') }}</dt>
                <dd class="col-sm-9">
                    {{ $backup['retention_days'] }}
                    <span class="form-text d-block">{{ __('ops.backup_retention_help') }}</span>
                </dd>
                <dt class="col-sm-3">{{ __('ops.last_backup') }}</dt>
                <dd class="col-sm-9">
                    @if($backup['last_backup_name'])
                        <code>{{ $backup['last_backup_name'] }}</code>
                        @if($backup['last_backup_at'])
                            <span class="text-muted-theme small">({{ $backup['last_backup_at'] }})</span>
                        @endif
                    @else
                        {{ __('ops.last_backup_none') }}
                    @endif
                </dd>
            </dl>
            <form method="POST" action="{{ route('superadmin.ops.backup') }}"
                  onsubmit="return confirm(@json(__('ops.backup_confirm')));">
                @csrf
                <button type="submit" class="btn btn-primary">{{ __('ops.backup_now') }}</button>
                <p class="form-text mb-0 mt-2">{{ __('ops.backup_now_help') }}</p>
            </form>
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="ops-schedule" aria-labelledby="ops-schedule-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="ops-schedule-heading">{{ __('ops.schedule_title') }}</h2>
            <p class="text-muted-theme">{{ __('ops.schedule_help') }}</p>
            <p class="form-text mb-3">{{ __('ops.schedule_cron_help') }}</p>
            <p class="mb-3">
                <a href="{{ route('superadmin.scheduled-tasks.index') }}">{{ __('ops.open_scheduled') }}</a>
            </p>
            @if($schedule === [])
                <p class="mb-0">{{ __('ops.schedule_empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('ops.col_command') }}</th>
                                <th scope="col">{{ __('ops.col_when') }}</th>
                                <th scope="col">{{ __('ops.col_cron') }}</th>
                                <th scope="col">{{ __('ops.col_next') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($schedule as $event)
                                <tr>
                                    <td><code>{{ $event['command'] }}</code></td>
                                    <td>{{ $event['human'] }}</td>
                                    <td><code>{{ $event['expression'] }}</code></td>
                                    <td class="small">{{ $event['next'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <section class="app-card card shadow-sm mb-4" id="ops-sessions" aria-labelledby="ops-sessions-heading">
        <div class="card-body">
            <h2 class="h5 page-title" id="ops-sessions-heading">{{ __('ops.session_title') }}</h2>
            <p class="text-muted-theme">{{ __('ops.session_help') }}</p>
            <p class="form-text mb-3">{{ __('ops.session_current_note') }}</p>
            <p class="mb-2">
                <span class="text-muted-theme">{{ __('ops.session_driver') }}:</span>
                <code>{{ $session['driver'] }}</code>
            </p>
            @if($session['session_count'] !== null)
                <p class="mb-3">
                    <span class="text-muted-theme">{{ __('ops.session_count') }}:</span>
                    {{ number_format($session['session_count']) }}
                </p>
            @endif
            @if(\Illuminate\Support\Facades\Lang::has($sessionDriverKey))
                <p class="form-text">{{ __($sessionDriverKey) }}</p>
            @endif
            @if($session['can_flush'])
                <p>{{ __('ops.session_can_flush') }}</p>
                <form method="POST" action="{{ route('superadmin.sessions.flush') }}"
                      onsubmit="return confirm(@json(__('ops.session_flush_confirm')));">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">{{ __('ops.session_flush') }}</button>
                    <p class="form-text mb-0 mt-2">{{ __('ops.session_flush_help') }}</p>
                </form>
            @else
                <p class="mb-2">{{ __('ops.session_cannot_flush', ['driver' => $session['driver']]) }}</p>
                <a href="{{ route('superadmin.security') }}">{{ __('ops.open_security') }}</a>
            @endif
        </div>
    </section>

    <p class="small text-muted-theme mb-0">
        <a href="{{ route('superadmin.observability.index') }}">{{ __('ops.open_observability') }}</a>
        · <a href="{{ route('superadmin.security') }}">{{ __('ops.open_security') }}</a>
        · @if(\Illuminate\Support\Facades\Route::has('superadmin.reports.show'))
            <a href="{{ route('superadmin.reports.show', 'queue') }}">{{ __('ops.open_reports_queue') }}</a>
            ·
        @endif
        <a href="{{ route('superadmin.audit.index') }}">{{ __('ops.open_audit') }}</a>
        · <a href="{{ route('superadmin.system-tests.index') }}">{{ __('superadmin.tile_system_tests') }}</a>
    </p>
</div>
@endsection
