@props([
    'course',
    'decorative' => true,
])

@php
    /** @var \App\Models\Course $course */
    $url = $course->coverUrl();
@endphp

<div {{ $attributes->merge(['class' => 'spims-cover']) }} @if($decorative) aria-hidden="true" @endif>
    <img
        src="{{ $url }}"
        alt="{{ $decorative ? '' : $course->title }}"
        class="spims-cover-photo"
        width="1200"
        height="675"
        loading="lazy"
        decoding="async"
        referrerpolicy="no-referrer"
    >
</div>
