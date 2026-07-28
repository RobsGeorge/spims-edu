@extends('layouts.app')

@section('title', __('superadmin.observability_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:920px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>
    <h1 class="page-title">{{ __('superadmin.observability_title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('superadmin.observability_desc') }}</p>

    <div class="row g-3">
        @foreach($stats as $key => $value)
            <div class="col-6 col-md-4">
                <div class="app-card card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="display-6 fw-bold page-title">{{ number_format($value) }}</div>
                        <div class="text-muted-theme text-uppercase small">{{ $key }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        <a href="{{ route('health') }}" class="btn btn-outline-primary" target="_blank" rel="noopener">
            <i class="bi bi-heart-pulse"></i> /health
        </a>
    </div>
</div>
@endsection
