@props([
    'name',
    'src' => null,
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'spims-avatar-sm',
        'lg' => 'spims-avatar-lg',
        default => 'spims-avatar-md',
    };
    $initials = collect(explode(' ', trim($name)))
        ->filter()
        ->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
        ->join('');
@endphp

<span
    {{ $attributes->merge(['class' => "spims-avatar $sizeClass"]) }}
    aria-label="{{ $name }}"
    title="{{ $name }}"
    role="img"
>
    @if($src)
        <img src="{{ $src }}" alt="{{ $name }}" loading="lazy">
    @else
        <span aria-hidden="true">{{ $initials }}</span>
    @endif
</span>
