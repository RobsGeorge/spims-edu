@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
])

<header {{ $attributes->merge(['class' => 'spims-page-header mb-4']) }}>
    @if($eyebrow)
        <p class="spims-page-eyebrow mb-1">{{ $eyebrow }}</p>
    @endif
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div class="min-w-0">
            <h1 class="spims-title mb-1">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-muted-theme mb-0">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="spims-page-actions d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>
</header>
