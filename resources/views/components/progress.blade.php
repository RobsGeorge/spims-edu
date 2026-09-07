@props([
    'value',
    'label' => null,
    'color' => null,
])

@php
    $pct = max(0, min(100, (int) $value));
    $colorClass = $color ? 'spims-progress--' . $color : '';
@endphp

<div {{ $attributes->merge(['class' => 'spims-progress-wrap']) }}>
    @if($label)
        <div class="spims-progress-label">
            <span>{{ $label }}</span>
            <span>{{ $pct }}%</span>
        </div>
    @endif
    <progress
        class="spims-progress {{ $colorClass }}"
        value="{{ $pct }}"
        max="100"
        aria-label="{{ $label ?? $pct . '%' }}"
        aria-valuenow="{{ $pct }}"
        aria-valuemin="0"
        aria-valuemax="100"
    ></progress>
</div>
