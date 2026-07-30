@props([
    'status' => 'neutral',
    'label' => null,
])

@php
    $tone = match (strtolower((string) $status)) {
        'success', 'active', 'enrolled', 'released', 'paid', 'open', 'present' => 'success',
        'warning', 'pending', 'waitlist', 'draft', 'partial' => 'warning',
        'danger', 'failed', 'rejected', 'suspended', 'locked', 'absent' => 'danger',
        'info', 'processing' => 'info',
        default => 'neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => 'spims-status-badge spims-status-'.$tone]) }}>
    {{ $label ?? $status }}
</span>
