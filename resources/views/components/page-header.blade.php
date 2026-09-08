@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'icon' => 'auto',
])

@php
    $iconKey = $icon;
    if ($icon === 'auto') {
        $iconKey = \App\Support\Ui\HeadingIcon::for(request()->route()?->getName());
    } elseif ($icon === null || $icon === '' || $icon === 'none') {
        $iconKey = null;
    }
@endphp

<header {{ $attributes->merge(['class' => 'spims-page-header']) }}>
    @if($eyebrow)
        <p class="spims-page-eyebrow mb-1">{{ $eyebrow }}</p>
    @endif
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div class="min-w-0 d-flex align-items-start gap-3">
            @if($iconKey)
                <span class="spims-heading-icon-wrap" aria-hidden="true">
                    <x-icon :name="$iconKey" size="lg" class="spims-heading-icon" />
                </span>
            @endif
            <div class="min-w-0">
                <h1 class="spims-title mb-1">{{ $title }}</h1>
                @if($subtitle)
                    <p class="spims-text-dim mb-0">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @isset($actions)
            <div class="spims-page-actions d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>
</header>
