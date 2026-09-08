@props([
    'name',
    'size' => 'md',
])

@php
use App\Support\Ui\IconVocabulary;

$biClass   = IconVocabulary::classFor($name);
$sizeClass = match($size) {
    'sm'    => 'spims-icon-sm',
    'lg'    => 'spims-icon-lg',
    default => 'spims-icon-md',
};
@endphp

<i {{ $attributes->merge(['class' => "bi {$biClass} {$sizeClass} spims-icon"]) }} aria-hidden="true"></i>
