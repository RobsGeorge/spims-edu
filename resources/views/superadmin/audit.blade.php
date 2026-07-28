@extends('layouts.app')

@section('title', __('superadmin.audit_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:960px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>
    <h1 class="page-title">{{ __('superadmin.audit_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('superadmin.audit_desc') }}</p>

    <div class="table-responsive app-card card shadow-sm">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('superadmin.when') }}</th>
                    <th>{{ __('superadmin.actor') }}</th>
                    <th>{{ __('superadmin.action') }}</th>
                    <th>{{ __('superadmin.entity') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at?->toDateTimeString() }}</td>
                        <td>{{ $log->actor?->email ?? '—' }}</td>
                        <td><code class="small">{{ $log->action }}</code></td>
                        <td class="small">{{ $log->entity_type }} {{ $log->entity_id }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted-theme p-3">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
