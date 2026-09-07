@extends('layouts.app')

@section('title', __('superadmin.title'))

@section('content')
<div class="sa-console animate-in">
    <header class="sa-console-hero app-card mb-4">
        <div class="d-flex flex-wrap align-items-start gap-3">
            <span class="badge bg-danger fs-6 px-3 py-2">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                {{ __('superadmin.role') }}
            </span>
            <div class="min-w-0 flex-grow-1">
                <h1 class="page-title mb-2">{{ __('superadmin.title') }}</h1>
                <p class="text-muted-theme mb-0">{{ __('superadmin.hub_desc') }}</p>
            </div>
        </div>
        <div class="sa-callout sa-callout-danger mt-3" role="note">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <div>
                <strong>{{ __('superadmin.bypass_title') }}</strong>
                <p class="mb-0">{{ __('superadmin.bypass_body') }}</p>
            </div>
        </div>
    </header>

    <nav class="sa-jump" aria-label="{{ __('superadmin.jump_label') }}">
        @foreach($sections as $section)
            <a class="sa-jump-link" href="#sa-{{ $section['id'] }}">{{ $section['title'] }}</a>
        @endforeach
    </nav>

    @foreach($sections as $section)
        <section id="sa-{{ $section['id'] }}" class="sa-section">
            <div class="sa-section-heading">
                <h2 class="h5 page-title mb-1">{{ $section['title'] }}</h2>
                <p class="text-muted-theme small mb-0">{{ $section['description'] }}</p>
            </div>
            <div class="row g-3">
                @foreach($section['links'] as $link)
                    @include('partials.hub-link-tile', ['link' => $link, 'col' => 'col-12 col-sm-6 col-xl-4'])
                @endforeach
            </div>
        </section>
    @endforeach

    <aside class="sa-roadmap app-card mt-4" aria-label="{{ __('superadmin.roadmap_title') }}">
        <h2 class="h6 page-title mb-2">{{ __('superadmin.roadmap_title') }}</h2>
        <p class="text-muted-theme small mb-3">{{ __('superadmin.roadmap_desc') }}</p>
        <ul class="sa-roadmap-list mb-0">
            <li class="is-done"><span class="sa-phase">SA1</span> {{ __('superadmin.roadmap_sa1_done') }}</li>
            <li class="is-done"><span class="sa-phase">SA2</span> {{ __('superadmin.roadmap_sa2_done') }}</li>
            <li><span class="sa-phase">SA3</span> {{ __('superadmin.roadmap_sa3') }}</li>
            <li><span class="sa-phase">SA4</span> {{ __('superadmin.roadmap_sa4') }}</li>
            <li><span class="sa-phase">SA5</span> {{ __('superadmin.roadmap_sa5') }}</li>
            <li><span class="sa-phase">SA6</span> {{ __('superadmin.roadmap_sa6') }}</li>
        </ul>
    </aside>
</div>
@endsection
