@extends('layouts.app')

@section('title', __('superadmin.observability_title'))

@section('content')
@php
    $statCopy = [
        'users' => ['label' => __('superadmin.stat_users'), 'help' => __('superadmin.stat_users_help')],
        'courses' => ['label' => __('superadmin.stat_courses'), 'help' => __('superadmin.stat_courses_help')],
        'offerings' => ['label' => __('superadmin.stat_offerings'), 'help' => __('superadmin.stat_offerings_help')],
        'enrollments' => ['label' => __('superadmin.stat_enrollments'), 'help' => __('superadmin.stat_enrollments_help')],
        'audit_logs' => [
            'label' => __('superadmin.stat_audit_logs'),
            'help' => __('superadmin.stat_audit_logs_help'),
            'url' => route('superadmin.audit.index'),
        ],
        'failed_jobs' => ['label' => __('superadmin.stat_failed_jobs'), 'help' => __('superadmin.stat_failed_jobs_help')],
        'jobs' => ['label' => __('superadmin.stat_jobs'), 'help' => __('superadmin.stat_jobs_help')],
    ];
@endphp
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>
    <x-page-header :title="__('superadmin.observability_title')" />
    <p class="text-muted-theme mb-4">{{ __('superadmin.observability_desc') }}</p>
    @include('partials.audit-entrance-banner', ['caption' => __('audit.entrance_from_observability')])

    <div class="row g-3 mb-4">
        @foreach($stats as $key => $value)
            @php $meta = $statCopy[$key] ?? ['label' => $key, 'help' => null]; @endphp
            <div class="col-6 col-md-4">
                @if(!empty($meta['url']))
                    <a href="{{ $meta['url'] }}" class="app-card card shadow-sm h-100 text-decoration-none" data-stat="{{ $key }}">
                        <div class="card-body text-center">
                            <div class="display-6 fw-bold page-title">{{ number_format($value) }}</div>
                            <div class="text-muted-theme text-uppercase small">{{ $meta['label'] }}</div>
                            @if(!empty($meta['help']))
                                <p class="form-text mb-0 mt-2">{{ $meta['help'] }}</p>
                            @endif
                        </div>
                    </a>
                @else
                    <div class="app-card card shadow-sm h-100" data-stat="{{ $key }}">
                        <div class="card-body text-center">
                            <div class="display-6 fw-bold page-title">{{ number_format($value) }}</div>
                            <div class="text-muted-theme text-uppercase small">{{ $meta['label'] }}</div>
                            @if(!empty($meta['help']))
                                <p class="form-text mb-0 mt-2">{{ $meta['help'] }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <dl class="row mb-4">
        <dt class="col-sm-4">{{ __('superadmin.queue_connection') }}</dt>
        <dd class="col-sm-8"><code>{{ $queueConnection }}</code></dd>
        <dt class="col-sm-4">{{ __('superadmin.backup_path') }}</dt>
        <dd class="col-sm-8"><code>{{ $backupPath }}</code></dd>
        <dt class="col-sm-4">{{ __('superadmin.last_backup') }}</dt>
        <dd class="col-sm-8">{{ $lastBackupAt ?? __('superadmin.last_backup_none') }}</dd>
    </dl>

    <div class="mt-4">
        <a href="{{ route('health') }}" class="btn btn-outline-primary" target="_blank" rel="noopener">
            <i class="bi bi-heart-pulse"></i> /health
        </a>
    </div>
</div>
@endsection
