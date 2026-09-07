@props([
    'name',
    'size' => 'md',
])

@php
/** @var array<string,string> Internal concept → Bootstrap Icon class vocabulary */
$vocabulary = [
    'course'          => 'bi-journal-text',
    'student'         => 'bi-person',
    'enrollment'      => 'bi-person-check',
    'grade'           => 'bi-star-half',
    'finance'         => 'bi-wallet2',
    'assessment'      => 'bi-clipboard-check',
    'attendance'      => 'bi-calendar-check',
    'credential'      => 'bi-award',
    'settings'        => 'bi-gear',
    'delete'          => 'bi-trash',
    'add'             => 'bi-plus-circle',
    'edit'            => 'bi-pencil',
    'view'            => 'bi-eye',
    'download'        => 'bi-download',
    'warning'         => 'bi-exclamation-triangle',
    'success'         => 'bi-check-circle',
    'empty-state'     => 'bi-inbox',
];

if (! array_key_exists($name, $vocabulary)) {
    throw new \InvalidArgumentException(
        "<x-icon> unknown concept key: '{$name}'. "
        . 'Available: ' . implode(', ', array_keys($vocabulary))
    );
}

$biClass   = $vocabulary[$name];
$sizeClass = match($size) {
    'sm'    => 'spims-icon-sm',
    'lg'    => 'spims-icon-lg',
    default => 'spims-icon-md',
};
@endphp

<i {{ $attributes->merge(['class' => "bi {$biClass} {$sizeClass}"]) }} aria-hidden="true"></i>
