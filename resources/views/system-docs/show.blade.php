@extends('layouts.app')

@section('title', $page['title'])

@section('content')
<div class="system-docs-article-shell animate-in">
    <nav class="mb-3" aria-label="{{ __('system_docs.title') }}">
        <a href="{{ route('system-docs.index', ['audience' => $page['audience']]) }}" class="text-decoration-none">
            <i class="bi bi-arrow-{{ ($localeDir ?? 'ltr') === 'rtl' ? 'right' : 'left' }}" aria-hidden="true"></i>
            {{ __('system_docs.back_to_index') }}
        </a>
    </nav>

    @if(!empty($usedFallback))
        <div class="alert alert-info" role="status">{{ __('system_docs.locale_fallback') }}</div>
    @endif

    <x-page-header :title="$page['title']" :subtitle="$page['summary']" />

    <p class="mb-3">
        <span class="badge spims-badge {{ $page['audience'] === 'client' ? 'spims-badge--info' : 'spims-badge--secondary' }}">
            {{ $page['audience'] === 'client' ? __('system_docs.audience_client') : __('system_docs.audience_technical') }}
        </span>
    </p>

    <x-card variant="panel">
        <article class="help-article-body p-3 p-md-4">
            {!! $bodyHtml !!}
        </article>
    </x-card>

    @if($siblings->count() > 1)
        <nav class="mt-4" aria-label="{{ __('system_docs.related') }}">
            <h2 class="h6 mb-3">{{ __('system_docs.related') }}</h2>
            <ul class="list-unstyled system-docs-related-list mb-0">
                @foreach($siblings as $sibling)
                    @if($sibling['slug'] === $page['slug'])
                        @continue
                    @endif
                    <li>
                        <x-card variant="quiet" class="h-100">
                            <div class="p-3">
                                <a href="{{ route('system-docs.show', $sibling['slug']) }}" class="stretched-link text-decoration-none">
                                    <i class="bi {{ $sibling['icon'] }}" aria-hidden="true"></i>
                                    <span class="fw-semibold">{{ $sibling['title'] }}</span>
                                </a>
                            </div>
                        </x-card>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
@endsection
