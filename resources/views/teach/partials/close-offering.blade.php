@if($closeToken ?? null)
<form method="POST" action="{{ route('teach.offerings.close', $offering) }}" id="closeOfferingForm">
    @csrf
    <input type="hidden" name="confirmation_token" value="{{ $closeToken }}">
</form>
<button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#closeOfferingModal">{{ __('teach.close_offering') }}</button>
<x-confirm-dialog
    id="closeOfferingModal"
    :title="__('completion.close_confirm_title')"
    :message="__('completion.close_confirm_body')"
    tone="danger"
>
    <x-slot:confirm>
        <button type="submit" form="closeOfferingForm" class="btn btn-danger">{{ __('completion.close_confirm') }}</button>
    </x-slot:confirm>
</x-confirm-dialog>
@endif
