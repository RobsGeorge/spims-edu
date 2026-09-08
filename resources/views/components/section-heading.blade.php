@props([
    'title',
    'icon' => null,
    'tag' => 'h2',
])

<{{ $tag }} {{ $attributes->merge(['class' => 'spims-section-heading']) }}>
    @if($icon)
        <span class="spims-heading-icon-wrap" aria-hidden="true">
            <x-icon :name="$icon" class="spims-heading-icon" />
        </span>
    @endif
    <span>{{ $title }}</span>
</{{ $tag }}>
