@extends('layouts.app')

@section('title', __('superadmin.security_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:720px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>
    <h1 class="page-title">
        <i class="bi bi-shield-lock text-danger"></i> {{ __('superadmin.security_title') }}
    </h1>
    <p class="text-muted-theme mb-4">{{ __('superadmin.security_desc') }}</p>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <p class="mb-1"><strong>{{ __('superadmin.session_driver') }}:</strong> {{ $sessionDriver }}</p>
            @if($sessionCount !== null)
                <p class="mb-3"><strong>{{ __('superadmin.session_count') }}:</strong> {{ $sessionCount }}</p>
            @endif
            <form method="POST" action="{{ route('superadmin.sessions.flush') }}" onsubmit="return confirm('OK?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-shield-lock-fill"></i> {{ __('superadmin.flush_others') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
