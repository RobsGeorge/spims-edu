@extends('layouts.app')
@section('title', $event->title)
@section('content')
<x-page-header
    :title="$event->title"
    :subtitle="$event->venue"
    :eyebrow="__('events.hub')"
>
    <x-slot:actions>
        <a href="{{ route('events.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('events.back') }}</a>
        <a href="{{ route('events.mine') }}" class="btn btn-outline-primary btn-sm">{{ __('events.mine') }}</a>
    </x-slot:actions>
</x-page-header>

{{-- Event details panel --}}
<x-card variant="panel" class="mb-4">
    @if($event->description)
        <p class="mb-4">{{ $event->description }}</p>
    @endif
    <dl class="row mb-0 small">
        <dt class="col-12 col-sm-3">{{ __('events.starts') }}</dt>
        <dd class="col-12 col-sm-9">{{ $event->starts_at?->timezone(config('app.timezone'))->format('M j, Y H:i') }}</dd>
        <dt class="col-12 col-sm-3">{{ __('events.ends') }}</dt>
        <dd class="col-12 col-sm-9">{{ $event->ends_at?->timezone(config('app.timezone'))->format('M j, Y H:i') }}</dd>
        @if($event->venue)
            <dt class="col-12 col-sm-3">{{ __('events.venue') }}</dt>
            <dd class="col-12 col-sm-9">{{ $event->venue }}</dd>
        @endif
        <dt class="col-12 col-sm-3">{{ __('events.spots_label') }}</dt>
        <dd class="col-12 col-sm-9">
            @if($event->capacity === null)
                {{ __('events.unlimited') }}
                @if($event->waitlist_enabled ?? false)
                    &middot; {{ __('events.waitlist_available') }}
                @endif
            @else
                @php
                    $spotsOpen = max(0, $event->capacity - $reservedCount);
                    $pctFilled = $event->capacity > 0
                        ? (int) min(100, round(($reservedCount / $event->capacity) * 100))
                        : 0;
                @endphp
                @if($spotsOpen === 0)
                    <span class="text-danger">{{ __('events.seats_full') }}</span>
                    @if($event->waitlist_enabled ?? false)
                        &middot; <span class="spims-text-dim">{{ __('events.waitlist_available') }}</span>
                    @endif
                @else
                    {{ __('events.spots', ['open' => $spotsOpen, 'capacity' => $event->capacity]) }}
                    @if($event->waitlist_enabled ?? false)
                        &middot; {{ __('events.waitlist_available') }}
                    @endif
                @endif
            @endif
        </dd>
    </dl>

    {{-- Capacity meter --}}
    @if($event->capacity !== null)
        <div class="mt-3">
            <x-progress :value="$pctFilled" :label="__('events.capacity_meter')" />
        </div>
    @endif
</x-card>

{{-- Reservation state --}}
@if($reservation)
    <x-card variant="quiet" class="mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle text-success" aria-hidden="true"></i>
                <span class="fw-semibold text-success">{{ __('events.you_are_registered') }}</span>
                <x-status-badge :status="$reservation->status->value" :label="__('events.status_'.$reservation->status->value)" />
            </div>
            @if($reservation->isOpen())
                <button type="button"
                        class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal"
                        data-bs-target="#cancel-confirm-show">
                    {{ __('events.cancel_reservation') }}
                </button>
            @endif
        </div>

        @if($qrPayload)
            @include('events.partials.check-in-ticket', ['payload' => $qrPayload])
        @endif
    </x-card>

    {{-- Cancel confirmation dialog --}}
    @if($reservation->isOpen())
        <x-confirm-dialog
            id="cancel-confirm-show"
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

@elseif($eligible)
    <form method="POST" action="{{ route('events.reserve', $event) }}">
        @csrf
        <button class="btn btn-primary">{{ __('events.reserve') }}</button>
    </form>
@else
    <p class="spims-text-dim mb-0">{{ __('events.ineligible_hint') }}</p>
@endif
@endsection
