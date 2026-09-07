@props([
    'label',
    'value',
    'icon' => null,
    'trend' => null,
])

@php
    $trendClass = match ($trend) {
        '+' => 'spims-stat-trend--up',
        '-' => 'spims-stat-trend--down',
        default => 'spims-stat-trend--neutral',
    };
@endphp

<div {{ $attributes->merge(['class' => 'spims-stat']) }}>
    @if($icon)
        <div class="spims-stat-icon" aria-hidden="true">
            <x-icon :name="$icon" size="lg" />
        </div>
    @endif
    <div class="spims-stat-body">
        <span class="spims-stat-value">{{ $value }}</span>
        <span class="spims-stat-label">{{ $label }}</span>
        @if($trend !== null)
            <span class="spims-stat-trend {{ $trendClass }}" aria-hidden="true">
                @if($trend === '+') &#9650; @elseif($trend === '-') &#9660; @else &mdash; @endif
            </span>
        @endif
    </div>
</div>
