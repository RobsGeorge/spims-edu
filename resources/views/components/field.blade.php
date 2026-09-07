@props([
    'label',
    'name',
    'error' => null,
    'hint' => null,
    'required' => false,
])

<div {{ $attributes->merge(['class' => 'spims-field mb-3']) }}>
    <label for="{{ $name }}" class="spims-field-label form-label">
        {{ $label }}
        @if($required)
            <span class="spims-field-required" aria-hidden="true">*</span>
        @endif
    </label>
    {{ $slot }}
    @if($hint && !$error)
        <div id="{{ $name }}-hint" class="spims-field-hint form-text">{{ $hint }}</div>
    @endif
    @if($error)
        <div id="{{ $name }}-error" class="spims-field-error invalid-feedback d-block" role="alert">{{ $error }}</div>
    @endif
</div>
