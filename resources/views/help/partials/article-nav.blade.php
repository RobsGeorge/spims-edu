@php
    $navItems = collect($siblings ?? [])
        ->filter(fn ($a) => ($a->slug ?? '') !== ($currentSlug ?? ''))
        ->take(6)
        ->values();
    $navLocale = $locale ?? app()->getLocale();
@endphp
@if($navItems->isNotEmpty())
    <aside class="help-related mt-5 pt-4 border-top" aria-label="{{ __('help.related') }}">
        <h2 class="h6 text-muted-theme mb-3">{{ __('help.related') }}</h2>
        <ul class="list-unstyled mb-0">
            @foreach($navItems as $related)
                @php
                    $relatedTitle = $related->localeFor($navLocale)?->title ?? $related->slug;
                @endphp
                <li class="mb-2">
                    <a href="{{ route('help.show', $related->slug) }}">{{ $relatedTitle }}</a>
                </li>
            @endforeach
        </ul>
    </aside>
@endif
