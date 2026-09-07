@props([
    'name',
    'accept' => null,
    'maxSize' => null,
])

<div
    x-data="{
        isDragover: false,
        error: '',
        fileName: '',
        maxSize: {{ $maxSize ? (int) $maxSize : 0 }},
        msgSize: @js(__('ui.file_drop_size_error', ['max' => $maxSize ? ceil((int)$maxSize / 1048576) . ' MB' : ''])),
        hint: @js(__('ui.file_drop_hint')),
        handleDrop(e) {
            this.isDragover = false;
            const file = e.dataTransfer?.files?.[0];
            if (file) this.validate(file);
        },
        handleChange(e) {
            const file = e.target?.files?.[0];
            if (file) this.validate(file);
        },
        validate(file) {
            this.error = '';
            if (this.maxSize && file.size > this.maxSize) {
                this.error = this.msgSize;
                return;
            }
            this.fileName = file.name;
        }
    }"
>
    <label
        class="spims-file-drop"
        :class="{ 'is-dragover': isDragover }"
        x-on:dragover.prevent="isDragover = true"
        x-on:dragleave.prevent="isDragover = false"
        x-on:drop.prevent="handleDrop($event)"
        x-on:click.prevent="$refs.filedropinput.click()"
        x-on:keydown.enter.prevent="$refs.filedropinput.click()"
        x-on:keydown.space.prevent="$refs.filedropinput.click()"
        tabindex="0"
        role="button"
        aria-label="{{ __('ui.file_drop_hint') }}"
    >
        <i class="spims-file-drop-icon bi bi-cloud-upload" aria-hidden="true"></i>
        <span class="spims-file-drop-hint" x-text="fileName || hint"></span>
        <span class="btn btn-sm btn-outline-primary mt-1" aria-hidden="true">{{ __('ui.file_drop_browse') }}</span>
        <input
            x-ref="filedropinput"
            type="file"
            name="{{ $name }}"
            class="visually-hidden"
            @if($accept) accept="{{ $accept }}" @endif
            x-on:change="handleChange($event)"
            aria-label="{{ __('ui.file_drop_hint') }}"
        >
    </label>
    <p class="spims-file-drop-error" x-show="error" x-text="error" role="alert" x-cloak></p>
</div>
