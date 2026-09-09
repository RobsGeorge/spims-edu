@extends('layouts.app')

@section('title', $localeRow?->title ?? $article->slug)

@section('content')
<div class="help-article animate-in" style="max-width:800px;margin:0 auto;">
    <nav class="mb-3" aria-label="{{ __('help.nav') }}">
        <a href="{{ route('help.index') }}" class="text-decoration-none">
            <i class="bi bi-arrow-{{ ($localeDir ?? 'ltr') === 'rtl' ? 'right' : 'left' }}" aria-hidden="true"></i>
            {{ __('help.back_to_index') }}
        </a>
        @if($article->category)
            <span class="text-muted-theme mx-1">·</span>
            <a href="{{ route('help.category', $article->category->slug) }}" class="text-decoration-none">
                @php
                    $catLabel = __('help.categories.'.$article->category->slug);
                    if ($catLabel === 'help.categories.'.$article->category->slug) {
                        $catLabel = $article->category->slug;
                    }
                @endphp
                {{ $catLabel }}
            </a>
        @endif
    </nav>

    @if(!empty($isPreview))
        <div class="alert alert-warning" role="status">{{ __('help.preview_banner') }}</div>
    @endif

    @if(!empty($usedFallback))
        <div class="alert alert-info" role="status">{{ __('help.locale_fallback') }}</div>
    @endif

    <header class="mb-4">
        <h1 class="page-title spims-title mb-2">{{ $localeRow?->title ?? $article->slug }}</h1>
        @if(($localeRow?->summary ?? '') !== '')
            <p class="text-muted-theme lead fs-6 mb-2">{{ $localeRow->summary }}</p>
        @endif
        <div class="d-flex flex-wrap gap-1">
            @include('help.partials.audience-badges', ['article' => $article])
        </div>
    </header>

    <article class="help-article-body">
        {!! $bodyHtml !!}
    </article>

    @include('help.partials.article-nav', [
        'siblings' => $siblings ?? collect(),
        'currentSlug' => $article->slug,
        'locale' => $locale ?? app()->getLocale(),
    ])
</div>
@endsection
