@extends('layouts.app')

@section('title', __('ui.nav_dashboard'))

@section('content')
<div class="spims-hero card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="spims-title">{{ __('ui.welcome_user', ['name' => auth()->user()->first_name]) }}</h1>
        <p class="text-muted-theme mb-0">{{ __('ui.dashboard_subheading') }}</p>
        <p class="mt-3 mb-0"><strong>{{ __('ui.roles') }}:</strong>
            {{ auth()->user()->roleTypes()->pluck('value')->join(', ') ?: '—' }}
        </p>
    </div>
</div>
@endsection
