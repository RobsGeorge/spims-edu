@php
    $showLock = $showLock ?? true;
    $showReopen = $showReopen ?? true;
    $buttonSize = $buttonSize ?? 'btn-sm';
@endphp
@if($showLock)
    <button type="button" class="btn {{ $buttonSize }} btn-danger" data-bs-toggle="modal" data-bs-target="#lockGradesModal">{{ __('assessment.lock_grades') }}</button>
@endif
@if($showReopen)
    <button type="button" class="btn {{ $buttonSize }} btn-secondary" data-bs-toggle="modal" data-bs-target="#reopenGradesModal">{{ __('assessment.reopen_grades') }}</button>
@endif

@if($showLock)
<form method="POST" action="{{ route('admin.gradebook.lock', $offering) }}" id="lockGradesForm">@csrf</form>
<x-confirm-dialog
    id="lockGradesModal"
    :title="__('teach.lock_confirm_title')"
    :message="__('teach.lock_confirm_body')"
    tone="danger"
>
    <x-slot:confirm>
        <button type="submit" form="lockGradesForm" class="btn btn-danger">{{ __('assessment.lock_grades') }}</button>
    </x-slot:confirm>
</x-confirm-dialog>
@endif

@if($showReopen)
<form method="POST" action="{{ route('admin.gradebook.reopen', $offering) }}" id="reopenGradesForm">@csrf</form>
<x-confirm-dialog
    id="reopenGradesModal"
    :title="__('teach.reopen_confirm_title')"
    :message="__('teach.reopen_confirm_body')"
    tone="primary"
>
    <x-slot:confirm>
        <button type="submit" form="reopenGradesForm" class="btn btn-primary">{{ __('assessment.reopen_grades') }}</button>
    </x-slot:confirm>
</x-confirm-dialog>
@endif
