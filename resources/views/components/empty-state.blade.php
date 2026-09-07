@props([
    'title',
    'message' => null,
    'icon' => 'bi-inbox',
])

<div {{ $attributes->merge(['class' => 'spims-empty-state text-center py-5 px-3']) }}>
    <div class="spims-empty-icon mb-3" aria-hidden="true">
        <i class="bi {{ $icon }}"></i>
    </div>
    <h2 class="h5 spims-title mb-2">{{ $title }}</h2>
    @if($message)
        <p class="spims-text-dim spims-empty-lede mb-3 mx-auto">{{ $message }}</p>
    @endif
    @isset($actions)
        <div class="d-flex flex-wrap justify-content-center gap-2">{{ $actions }}</div>
    @endisset
</div>
