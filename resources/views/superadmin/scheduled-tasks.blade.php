@extends('layouts.app')

@section('title', __('superadmin.scheduled_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>
    <h1 class="page-title">{{ __('superadmin.scheduled_title') }}</h1>
    <p class="text-muted-theme mb-3">{{ __('superadmin.scheduled_desc') }}</p>
    <p class="small text-muted-theme mb-3">{{ __('superadmin.scheduled_live_help') }}</p>
    <p class="small text-muted-theme mb-4">{{ __('superadmin.scheduled_prune_help') }}</p>
    @include('partials.config-entrance-banner')
    @include('partials.audit-entrance-banner', ['caption' => __('audit.entrance_from_scheduled')])
    @include('partials.ops-entrance-banner', ['caption' => __('ops.entrance_from_scheduled')])

    <div class="table-responsive app-card card shadow-sm">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>{{ __('ops.col_command') }}</th>
                    <th>{{ __('ops.col_when') }}</th>
                    <th>{{ __('ops.col_cron') }}</th>
                    <th>{{ __('ops.col_next') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $task)
                    <tr>
                        <td><code>{{ $task['command'] }}</code></td>
                        <td>{{ $task['human'] ?? $task['schedule'] ?? '' }}</td>
                        <td><code>{{ $task['expression'] ?? '' }}</code></td>
                        <td class="small">{{ $task['next'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">{{ __('ops.schedule_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="form-text mt-3 mb-0">{{ __('ops.schedule_cron_help') }}</p>
</div>
@endsection
