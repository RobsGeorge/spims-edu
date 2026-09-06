@extends('layouts.app')

@section('title', __('demo.title'))

@section('content')
<section class="spims-demo animate-in">
    <header class="spims-demo-hero">
        <p class="spims-page-eyebrow mb-1">{{ __('demo.eyebrow') }}</p>
        <h1 class="spims-demo-heading">{{ __('demo.heading') }}</h1>
        <p class="spims-demo-lead text-muted-theme">{{ __('demo.lead') }}</p>
    </header>

    <aside class="app-card spims-demo-tools mb-4" aria-labelledby="demo-tools-title">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="h5 spims-title mb-1" id="demo-tools-title">{{ __('demo.tools_title') }}</h2>
                <p class="text-muted-theme mb-0">{{ __('demo.tools_body') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('demo.seed') }}" id="demoSeedForm">
                    @csrf
                    <input type="hidden" name="confirmation_token" value="{{ $seedToken }}">
                </form>
                <form method="POST" action="{{ route('demo.reset') }}" id="demoResetForm">
                    @csrf
                    <input type="hidden" name="confirmation_token" value="{{ $resetToken }}">
                </form>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#demoSeedModal">
                    {{ __('demo.seed') }}
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#demoResetModal">
                    {{ __('demo.reset') }}
                </button>
            </div>
        </div>
        <p class="small text-muted-theme mb-0 mt-3">{{ __('demo.shared_db') }}</p>
    </aside>

    <x-confirm-dialog
        id="demoSeedModal"
        :title="__('demo.seed_confirm_title')"
        :message="__('demo.seed_confirm_body')"
        tone="primary"
    >
        <x-slot:confirm>
            <button type="submit" form="demoSeedForm" class="btn btn-primary">{{ __('demo.seed') }}</button>
        </x-slot:confirm>
    </x-confirm-dialog>

    <x-confirm-dialog
        id="demoResetModal"
        :title="__('demo.reset_confirm_title')"
        :message="__('demo.reset_confirm_body')"
        tone="danger"
    >
        <x-slot:confirm>
            <button type="submit" form="demoResetForm" class="btn btn-danger">{{ __('demo.reset') }}</button>
        </x-slot:confirm>
    </x-confirm-dialog>

    @if(! $ready)
        <div class="alert alert-warning spims-demo-ready" role="status">
            {{ __('demo.needs_seed') }}
        </div>
    @endif

    <h2 class="h5 spims-title mb-3">{{ __('demo.featured_title') }}</h2>
    <div class="row g-3 mb-4">
        @foreach($featured as $persona)
            @include('demo.partials.persona-tile', ['persona' => $persona])
        @endforeach
    </div>

    <h2 class="h5 spims-title mb-3">{{ __('demo.more_title') }}</h2>
    <div class="row g-3">
        @foreach($more as $persona)
            @include('demo.partials.persona-tile', ['persona' => $persona, 'compact' => true])
        @endforeach
    </div>
</section>
@endsection
