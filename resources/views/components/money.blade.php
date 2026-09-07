@props([
    'minor',
    'currency',
])

@php
use App\Enums\Currency;
use App\Support\Money;

if (! is_int($minor)) {
    throw new \InvalidArgumentException(
        '<x-money> :minor must be an integer. Received: ' . get_debug_type($minor)
    );
}

if (! ($currency instanceof Currency)) {
    throw new \InvalidArgumentException(
        '<x-money> :currency must be a Currency enum instance. Received: ' . get_debug_type($currency)
    );
}

$formatted = Money::fromMinor($minor, $currency)->format();
@endphp

<span {{ $attributes->merge(['class' => 'spims-money']) }}>{{ $formatted }}</span>
