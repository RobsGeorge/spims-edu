@props([
    'id',
    'title',
    'message',
    'confirmLabel' => null,
    'cancelLabel' => null,
    'tone' => 'danger',
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content spims-confirm-dialog">
            <div class="modal-header border-0">
                <h2 class="modal-title h5 spims-title" id="{{ $id }}-title">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0 text-muted-theme">{{ $message }}</p>
                {{ $slot }}
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    {{ $cancelLabel ?? __('ui.cancel') }}
                </button>
                @isset($confirm)
                    {{ $confirm }}
                @else
                    <button type="submit" class="btn btn-{{ $tone === 'danger' ? 'danger' : 'primary' }}">
                        {{ $confirmLabel ?? __('ui.confirm') }}
                    </button>
                @endisset
            </div>
        </div>
    </div>
</div>
