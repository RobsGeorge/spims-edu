@extends('layouts.app')

@section('title', __('system_docs.title'))

@section('content')
<div class="system-docs animate-in" style="max-width:960px;margin-inline:auto;">
    <x-page-header :title="__('system_docs.title')" :subtitle="__('system_docs.subtitle')" />

    @if($isGuest)
        <p class="spims-text-dim mb-4" role="status">{{ __('system_docs.guest_banner') }}</p>
    @endif

    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="{{ __('system_docs.audience_nav') }}">
        <a href="{{ route('system-docs.index') }}"
           class="btn btn-sm {{ $audienceFilter ? 'btn-outline-secondary' : 'btn-secondary' }}">
            {{ __('system_docs.audience_all') }}
        </a>
        <a href="{{ route('system-docs.index', ['audience' => 'client']) }}"
           class="btn btn-sm {{ $audienceFilter === 'client' ? 'btn-secondary' : 'btn-outline-secondary' }}">
            {{ __('system_docs.audience_client') }}
        </a>
        @unless($isGuest)
            <a href="{{ route('system-docs.index', ['audience' => 'technical']) }}"
               class="btn btn-sm {{ $audienceFilter === 'technical' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                {{ __('system_docs.audience_technical') }}
            </a>
        @endunless
    </nav>

    @php
        $clientPages = $pages->where('audience', 'client')->values();
        $technicalPages = $pages->where('audience', 'technical')->values();
    @endphp

    @if($clientPages->isNotEmpty() && ($audienceFilter === null || $audienceFilter === 'client'))
        <section class="mb-4" aria-labelledby="system-docs-client-heading">
            <h2 id="system-docs-client-heading" class="h5 mb-3">{{ __('system_docs.audience_client') }}</h2>
            <div class="row g-3">
                @foreach($clientPages as $page)
                    <div class="col-12 col-md-6">
                        <x-card variant="panel" class="h-100">
                            <div class="p-3 d-flex flex-column h-100 gap-2">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi {{ $page['icon'] }} fs-4" aria-hidden="true"></i>
                                    <div>
                                        <a href="{{ route('system-docs.show', $page['slug']) }}" class="stretched-link text-decoration-none">
                                            <span class="fw-semibold">{{ $page['title'] }}</span>
                                        </a>
                                        <p class="spims-text-dim mb-0 small">{{ $page['summary'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </x-card>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($technicalPages->isNotEmpty() && ($audienceFilter === null || $audienceFilter === 'technical'))
        <section class="mb-4" aria-labelledby="system-docs-tech-heading">
            <h2 id="system-docs-tech-heading" class="h5 mb-3">{{ __('system_docs.audience_technical') }}</h2>
            <div class="row g-3">
                @foreach($technicalPages as $page)
                    <div class="col-12 col-md-6">
                        <x-card variant="quiet" class="h-100">
                            <div class="p-3 d-flex flex-column h-100 gap-2">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi {{ $page['icon'] }} fs-4" aria-hidden="true"></i>
                                    <div>
                                        <a href="{{ route('system-docs.show', $page['slug']) }}" class="stretched-link text-decoration-none">
                                            <span class="fw-semibold">{{ $page['title'] }}</span>
                                        </a>
                                        <p class="spims-text-dim mb-0 small">{{ $page['summary'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </x-card>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($pages->isEmpty())
        <x-empty-state
            :title="__('system_docs.empty_title')"
            :message="__('system_docs.empty_desc')"
        />
    @endif

    <x-card variant="bare" class="mt-4">
        <p class="spims-text-dim mb-0 small">
            {{ __('system_docs.help_crosslink_prefix') }}
            <a href="{{ route('help.index') }}">{{ __('help.title') }}</a>
        </p>
    </x-card>
</div>
@endsection
