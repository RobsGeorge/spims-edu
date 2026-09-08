@extends('layouts.app')

@section('title', __('audit.explorer_title'))

@section('content')
@php
    $activeFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp
<div class="audit-explorer animate-in">
    <nav class="mb-3 small" aria-label="{{ __('audit.explorer_title') }}">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('audit.crumb_console') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('audit.explorer_title') }}</span>
    </nav>

    <header class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <x-page-header :title="__('audit.explorer_title')" />
            <p class="text-muted-theme mb-2">{{ __('audit.explorer_lead') }}</p>
            <p class="small text-muted-theme mb-0">{{ __('audit.explorer_help') }}</p>
        </div>
        <span class="audit-append-badge">{{ __('audit.append_only') }}</span>
    </header>

    <aside class="sa-callout sa-callout-danger mb-4" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('audit.retention_title') }}</strong>
            <p class="mb-1">{{ __('audit.retention_help', [
                'days' => $retentionDays,
                'prefixes' => implode(', ', $protectedPrefixes),
            ]) }}</p>
            <p class="mb-0 small">
                {{ __('audit.retention_command', ['command' => 'spims:prune-audit-logs']) }}
                <a href="{{ route('superadmin.scheduled-tasks.index') }}">{{ __('audit.open_scheduled') }}</a>
            </p>
        </div>
    </aside>

    <section class="app-card p-3 mb-4">
        <h2 class="h6 page-title">{{ __('audit.filter_title') }}</h2>
        <p class="small text-muted-theme mb-3">{{ __('audit.filter_help') }}</p>

        <p class="small mb-2">{{ __('audit.prefix_hints_help') }}</p>
        <div class="audit-prefix-row mb-3" role="list">
            @foreach($prefixHints as $prefix)
                <a role="listitem"
                   class="audit-prefix-chip {{ ($filters['action'] ?? '') === $prefix ? 'is-active' : '' }}"
                   href="{{ route('superadmin.audit.index', array_merge($activeFilters, ['action' => $prefix])) }}">
                    <code>{{ $prefix }}</code>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('superadmin.audit.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="audit-actor">{{ __('audit.filter_actor') }}</label>
                <input id="audit-actor" name="actor" type="search" class="form-control"
                       value="{{ $filters['actor'] ?? '' }}"
                       placeholder="{{ __('audit.filter_actor_placeholder') }}" autocomplete="off">
                <p class="form-text mb-0">{{ __('audit.filter_actor_help') }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="audit-action">{{ __('audit.filter_action') }}</label>
                <input id="audit-action" name="action" type="search" class="form-control"
                       value="{{ $filters['action'] ?? '' }}"
                       placeholder="{{ __('audit.filter_action_placeholder') }}" autocomplete="off">
                <p class="form-text mb-0">{{ __('audit.filter_action_help') }}</p>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="audit-entity-type">{{ __('audit.filter_entity_type') }}</label>
                <input id="audit-entity-type" name="entity_type" class="form-control"
                       value="{{ $filters['entity_type'] ?? '' }}">
                <p class="form-text mb-0">{{ __('audit.filter_entity_type_help') }}</p>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="audit-entity-id">{{ __('audit.filter_entity_id') }}</label>
                <input id="audit-entity-id" name="entity_id" class="form-control"
                       value="{{ $filters['entity_id'] ?? '' }}">
                <p class="form-text mb-0">{{ __('audit.filter_entity_id_help') }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="audit-from">{{ __('audit.filter_from') }}</label>
                <input id="audit-from" name="from" type="date" class="form-control"
                       value="{{ $filters['from'] ?? '' }}">
                <p class="form-text mb-0">{{ __('audit.filter_from_help') }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="audit-to">{{ __('audit.filter_to') }}</label>
                <input id="audit-to" name="to" type="date" class="form-control"
                       value="{{ $filters['to'] ?? '' }}">
                <p class="form-text mb-0">{{ __('audit.filter_to_help') }}</p>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="audit-request">{{ __('audit.filter_request_id') }}</label>
                <input id="audit-request" name="request_id" class="form-control"
                       value="{{ $filters['request_id'] ?? '' }}">
                <p class="form-text mb-0">{{ __('audit.filter_request_id_help') }}</p>
            </div>
            <div class="col-md-2 d-flex flex-wrap gap-2">
                <button class="btn btn-primary">{{ __('audit.apply_filters') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('superadmin.audit.index') }}">{{ __('audit.clear_filters') }}</a>
            </div>
        </form>
    </section>

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <p class="mb-1">{{ __('audit.matched', ['count' => $matched]) }}</p>
            <p class="small text-muted-theme mb-0">{{ __('audit.results_help') }}</p>
        </div>
        @if($canExport)
            <div class="text-md-end">
                <a class="btn btn-outline-primary" href="{{ route('superadmin.audit.export', $activeFilters) }}">
                    <i class="bi bi-download" aria-hidden="true"></i>
                    {{ __('audit.export') }}
                </a>
                <p class="form-text mb-0">{{ __('audit.export_help', ['cap' => $exportCap]) }}</p>
            </div>
        @endif
    </div>

    @if($matched > $exportCap)
        <div class="alert alert-warning">{{ __('audit.export_over_cap', ['cap' => $exportCap, 'matched' => $matched]) }}</div>
    @endif

    <div class="table-responsive app-card card shadow-sm">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('audit.col_when') }}</th>
                    <th>{{ __('audit.col_actor') }}</th>
                    <th>{{ __('audit.col_actor_role') }}</th>
                    <th>{{ __('audit.col_action') }}</th>
                    <th>{{ __('audit.col_entity') }}</th>
                    <th>{{ __('audit.col_request_id') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
                        <td class="small">
                            @if($log->actor)
                                <a href="{{ route('superadmin.audit.index', array_merge($activeFilters, ['actor' => $log->actor->email])) }}">
                                    {{ $log->actor->email }}
                                </a>
                            @else
                                <span class="text-muted-theme">{{ __('audit.system_actor') }}</span>
                            @endif
                        </td>
                        <td class="small"><code>{{ $log->actor_role ?: '—' }}</code></td>
                        <td>
                            <a href="{{ route('superadmin.audit.show', $log) }}"><code>{{ $log->action }}</code></a>
                        </td>
                        <td class="small">
                            @if($log->entity_type || $log->entity_id)
                                {{ $log->entity_type }} {{ $log->entity_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="small"><code>{{ $log->request_id ?: '—' }}</code></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('superadmin.audit.show', $log) }}">
                                {{ __('audit.open_detail') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-3">
                            <p class="mb-1">{{ __('audit.empty') }}</p>
                            <p class="small text-muted-theme mb-0">{{ __('audit.empty_help') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
