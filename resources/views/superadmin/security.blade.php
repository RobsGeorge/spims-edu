@extends('layouts.app')

@section('title', __('superadmin.security_title'))

@section('content')
@php
    $canFlush = $sessionDriver === 'database' && $sessionCount !== null;
    $driverKey = 'ops.session_driver_'.$sessionDriver;
@endphp
<div class="hub-page animate-in" style="max-width:720px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>
    <h1 class="page-title">
        <i class="bi bi-shield-lock text-danger"></i> {{ __('superadmin.security_title') }}
    </h1>
    <p class="text-muted-theme mb-2">{{ __('superadmin.security_desc') }}</p>
    <p class="small text-muted-theme mb-4">{{ __('superadmin.security_flush_help') }}</p>
    @include('partials.people-entrance-banner', ['caption' => __('superadmin.entrance_from_security')])
    @include('partials.features-entrance-banner')
    @include('partials.audit-entrance-banner', ['caption' => __('audit.entrance_from_security')])
    @include('partials.ops-entrance-banner', ['caption' => __('ops.entrance_from_security')])
    @include('partials.status-entrance-banner', ['caption' => __('status.entrance_from_security')])
    @include('partials.access-entrance-banner', ['caption' => __('access.entrance_from_security')])

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <p class="mb-1"><strong>{{ __('superadmin.session_driver') }}:</strong> <code>{{ $sessionDriver }}</code></p>
            <p class="form-text mb-3">{{ __('ops.session_help') }}</p>
            @if(\Illuminate\Support\Facades\Lang::has($driverKey))
                <p class="form-text">{{ __($driverKey) }}</p>
            @endif
            @if($canFlush)
                <p class="mb-3"><strong>{{ __('superadmin.session_count') }}:</strong> {{ $sessionCount }}</p>
                <p class="small text-muted-theme mb-3">{{ __('ops.session_can_flush') }}</p>
                <form method="POST" action="{{ route('superadmin.sessions.flush') }}"
                      onsubmit="return confirm(@json(__('superadmin.flush_confirm')));">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-shield-lock-fill"></i> {{ __('superadmin.flush_others') }}
                    </button>
                    <p class="form-text mb-0 mt-2">{{ __('superadmin.flush_confirm_help') }}</p>
                </form>
            @else
                <p class="mb-2">{{ __('superadmin.sessions_flush_unsupported', ['driver' => $sessionDriver]) }}</p>
                <p class="form-text mb-0">{{ __('ops.session_cannot_flush', ['driver' => $sessionDriver]) }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
