@props([
    'variant',
    'tag' => 'div',
])

@php
$allowed = ['panel', 'quiet', 'bare'];
if (! in_array($variant, $allowed, true)) {
    throw new \InvalidArgumentException(
        "Unknown card variant: '{$variant}'. Allowed values: " . implode(', ', $allowed)
    );
}

$classes = "spims-card spims-card-{$variant}";
if ($variant !== 'bare') {
    $classes .= ' academic-card';
}
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</{{ $tag }}>
