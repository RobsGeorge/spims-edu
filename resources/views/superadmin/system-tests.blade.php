@extends('layouts.app')

@section('title', __('superadmin.system_tests_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:800px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
    </div>
    <h1 class="page-title">{{ __('superadmin.system_tests_title') }}</h1>
    <p class="text-muted-theme mb-3">{{ __('superadmin.system_tests_desc') }}</p>
    <aside class="sa-callout sa-callout-info mb-4" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ __('superadmin.system_tests_d8_title') }}</strong>
            <p class="mb-0">{{ __('superadmin.system_tests_d8') }}</p>
        </div>
    </aside>
    @include('partials.ops-entrance-banner', ['caption' => __('ops.entrance_from_tests')])
    @include('partials.status-entrance-banner', ['caption' => __('status.entrance_from_tests')])

    <div class="row g-3">
        @foreach($suites as $suite)
            <div class="col-md-6">
                <div class="app-card card shadow-sm h-100">
                    <div class="card-body">
                        <h3 class="h6 page-title mb-2">{{ __('superadmin.suite') }}: {{ $suite }}</h3>
                        <code class="small">{{ __('superadmin.run_hint', ['suite' => $suite]) }}</code>
                        <p class="form-text mb-0 mt-2">{{ __('superadmin.system_tests_copy_help') }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
