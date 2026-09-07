@props([
    'id',
    'title',
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'spims-modal-sm',
        'lg' => 'spims-modal-lg',
        default => 'spims-modal-md',
    };
@endphp

<div
    x-data="{ open: false }"
    x-on:open-modal-{{ $id }}.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-init="$watch('open', v => v && $nextTick(() => $refs.dialog.focus()))"
>
    <div
        x-show="open"
        x-transition
        x-cloak
        class="spims-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        id="{{ $id }}"
        x-on:click.self="open = false"
    >
        <div
            class="spims-modal-dialog {{ $sizeClass }}"
            x-ref="dialog"
            tabindex="-1"
        >
            <div class="spims-modal-content">
                <div class="spims-modal-header">
                    <h2 class="spims-modal-title spims-title" id="{{ $id }}-title">{{ $title }}</h2>
                    <button
                        type="button"
                        class="btn-close"
                        x-on:click="open = false"
                        aria-label="{{ __('ui.close') }}"
                    ></button>
                </div>
                <div class="spims-modal-body">{{ $slot }}</div>
                @isset($footer)
                    <div class="spims-modal-footer">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </div>
</div>
