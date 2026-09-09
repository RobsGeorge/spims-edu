@extends('layouts.app')
@section('title', __('events.mine_title'))
@section('content')
<x-page-header
    :title="__('events.mine_title')"
    :subtitle="__('events.mine_subtitle')"
    :eyebrow="__('events.hub')"
>
    <x-slot:actions>
        <a href="{{ route('events.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('events.back') }}</a>
    </x-slot:actions>
</x-page-header>

@forelse($reservations as $reservation)
    @php $event = $reservation->event; @endphp
    <x-card variant="quiet" tag="article" class="mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-3">
            <div class="min-width-0">
                <p class="fw-semibold mb-1 spims-title">{{ $event?->title ?? __('events.not_found') }}</p>
                <p class="small spims-text-dim mb-2">
                    @if($event)
                        {{ $event->starts_at?->timezone(config('app.timezone'))->format('M j, H:i') }}
                        @if($event->venue)
                            &middot; {{ $event->venue }}
                        @endif
                    @endif
                </p>
                <x-status-badge :status="$reservation->status->value" :label="__('events.status_'.$reservation->status->value)" />
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($event)
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('events.show', $event) }}">{{ __('events.view') }}</a>
                    @if($reservation->isOpen())
                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal"
                                data-bs-target="#cancel-confirm-{{ $reservation->id }}">
                            {{ __('events.cancel_reservation') }}
                        </button>
                    @endif
                @endif
            </div>
        </div>

        @if(!empty($qrById[$reservation->id]))
            @include('events.partials.check-in-ticket', ['payload' => $qrById[$reservation->id]])
        @endif
    </x-card>

    {{-- Cancel confirmation dialog --}}
    @if($event && $reservation->isOpen())
        <x-confirm-dialog
            id="cancel-confirm-{{ $reservation->id }}"
            :title="__('events.confirm_cancel_title')"
            :message="__('events.confirm_cancel_message')"
            tone="danger"
        >
            <x-slot:confirm>
                <form method="POST" action="{{ route('events.cancel', $event) }}">
                    @csrf
                    <button type="submit" class="btn btn-danger">{{ __('events.cancel_reservation') }}</button>
                </form>
            </x-slot:confirm>
        </x-confirm-dialog>
    @endif
@empty
    <x-empty-state :title="__('events.mine_empty')" icon="bi-calendar-check" />
@endforelse
@endsection
