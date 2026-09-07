@props([
    'value',
    'variant' => 'default',
])

@php
use Illuminate\Support\Str;

if (! ($value instanceof \BackedEnum)) {
    throw new \InvalidArgumentException(
        '<x-badge> requires a backed enum instance. Received: ' . get_debug_type($value)
    );
}

$langFile  = Str::snake(class_basename($value::class));
$label     = __("{$langFile}.{$value->value}");

// Semantic colour mapping based on common enum value names
$colorMap = [
    'OPEN'      => 'info',
    'PARTIAL'   => 'warning',
    'PAID'      => 'success',
    'VOID'      => 'secondary',
    'REFUNDED'  => 'secondary',
    'active'    => 'success',
    'inactive'  => 'secondary',
    'draft'     => 'secondary',
    'published' => 'success',
    'archived'  => 'secondary',
    'pending'   => 'warning',
    'approved'  => 'success',
    'rejected'  => 'danger',
    'suspended' => 'danger',
];

$tone = $colorMap[$value->value] ?? 'default';

$toneClasses = match ($tone) {
    'success'   => 'spims-badge--success',
    'warning'   => 'spims-badge--warning',
    'danger'    => 'spims-badge--danger',
    'info'      => 'spims-badge--info',
    'secondary' => 'spims-badge--secondary',
    default     => 'spims-badge--default',
};
@endphp

<span {{ $attributes->merge(['class' => "badge spims-badge {$toneClasses}"]) }}>{{ $label }}</span>
