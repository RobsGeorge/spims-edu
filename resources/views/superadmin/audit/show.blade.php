@extends('layouts.app')

@section('title', __('audit.detail_title'))

@section('content')
@php
    $pretty = static function (?array $payload): string {
        if ($payload === null || $payload === []) {
            return '';
        }

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };
    $beforeJson = $pretty($log->before);
    $afterJson = $pretty($log->after);
    $prefix = $log->actionPrefix();
@endphp
<div class="audit-explorer audit-detail animate-in">
    <nav class="mb-3 small" aria-label="{{ __('audit.detail_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('audit.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <a href="{{ route('superadmin.audit.index') }}">{{ __('audit.explorer_title') }}</a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme"><code>{{ $log->action }}</code></span>
    </nav>

    <header class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h1 class="page-title mb-2">{{ __('audit.detail_title') }}</h1>
            <p class="text-muted-theme mb-2">{{ __('audit.detail_lead') }}</p>
            <p class="small text-muted-theme mb-0">{{ __('audit.detail_help') }}</p>
        </div>
        <span class="audit-append-badge">{{ __('audit.append_only') }}</span>
    </header>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('superadmin.audit.index', ['action' => $prefix]) }}">
            {{ __('audit.filter_this_action') }} <code>{{ $prefix }}</code>
        </a>
        @if($log->actor)
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('superadmin.audit.index', ['actor' => $log->actor->email]) }}">
                {{ __('audit.filter_this_actor') }}
            </a>
        @endif
        @if($log->request_id)
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('superadmin.audit.index', ['request_id' => $log->request_id]) }}">
                {{ __('audit.filter_this_request') }}
            </a>
        @endif
        @if($log->entity_type)
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('superadmin.audit.index', array_filter([
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
            ])) }}">
                {{ __('audit.filter_this_entity') }}
            </a>
        @endif
        @if($canOpenDossier && $log->actor)
            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.show', $log->actor) }}">
                {{ __('audit.open_actor') }}
            </a>
        @endif
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('audit.meta_title') }}</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">{{ __('audit.col_when') }}</dt>
                    <dd class="col-sm-8">{{ $log->created_at?->timezone(config('app.timezone'))->toIso8601String() }}</dd>
                    <dt class="col-sm-4">{{ __('audit.col_actor') }}</dt>
                    <dd class="col-sm-8">
                        @if($log->actor)
                            {{ $log->actor->email }}
                            <p class="form-text mb-0">{{ $log->actor->displayName() }}</p>
                        @else
                            {{ __('audit.system_actor') }}
                        @endif
                    </dd>
                    <dt class="col-sm-4">{{ __('audit.col_actor_role') }}</dt>
                    <dd class="col-sm-8"><code>{{ $log->actor_role ?: '—' }}</code></dd>
                    <dt class="col-sm-4">{{ __('audit.col_action') }}</dt>
                    <dd class="col-sm-8"><code>{{ $log->action }}</code></dd>
                    <dt class="col-sm-4">{{ __('audit.col_entity_type') }}</dt>
                    <dd class="col-sm-8">{{ $log->entity_type ?: '—' }}</dd>
                    <dt class="col-sm-4">{{ __('audit.col_entity_id') }}</dt>
                    <dd class="col-sm-8"><code>{{ $log->entity_id ?: '—' }}</code></dd>
                </dl>
            </section>

            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('audit.context_title') }}</h2>
                <p class="small text-muted-theme">{{ __('audit.context_help') }}</p>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">{{ __('audit.col_request_id') }}</dt>
                    <dd class="col-sm-8">
                        @if($log->request_id)
                            <code>{{ $log->request_id }}</code>
                        @else
                            <span class="text-muted-theme">{{ __('audit.no_request_id') }}</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">{{ __('audit.col_ip') }}</dt>
                    <dd class="col-sm-8">
                        @if($log->ip)
                            <code>{{ $log->ip }}</code>
                        @else
                            <span class="text-muted-theme">{{ __('audit.no_ip') }}</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">{{ __('audit.col_user_agent') }}</dt>
                    <dd class="col-sm-8">
                        @if($log->user_agent)
                            <code class="small">{{ $log->user_agent }}</code>
                        @else
                            <span class="text-muted-theme">{{ __('audit.no_ua') }}</span>
                        @endif
                    </dd>
                </dl>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="app-card p-3 mb-4">
                <h2 class="h6 page-title">{{ __('audit.payload_title') }}</h2>
                <p class="small text-muted-theme">{{ __('audit.payload_help') }}</p>
                <h3 class="h6">{{ __('audit.col_before') }}</h3>
                @if($beforeJson === '')
                    <p class="text-muted-theme small">{{ __('audit.payload_empty') }}</p>
                @else
                    <pre class="audit-json" dir="ltr">{{ $beforeJson }}</pre>
                @endif
                <h3 class="h6">{{ __('audit.col_after') }}</h3>
                @if($afterJson === '')
                    <p class="text-muted-theme small mb-0">{{ __('audit.payload_empty') }}</p>
                @else
                    <pre class="audit-json mb-0" dir="ltr">{{ $afterJson }}</pre>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
