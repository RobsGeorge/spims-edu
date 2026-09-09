@extends('layouts.app')
@section('title', __('events.catalog_title'))
@section('content')
<x-page-header
    :title="__('events.catalog_title')"
    :subtitle="__('events.catalog_subtitle')"
    :eyebrow="__('events.hub')"
>
    <x-slot:actions>
        <a href="{{ route('events.mine') }}" class="btn btn-outline-primary btn-sm">{{ __('events.mine') }}</a>
    </x-slot:actions>
</x-page-header>

@if($events->isEmpty())
    <x-empty-state :title="__('events.catalog_empty')" icon="bi-calendar-event" />
@else
<div class="grid-auto-md mt-4">
@foreach($events as $event)
    @php
        $reservation = $mine[$event->id] ?? null;
        $reserved = (int) $event->reserved_count;
        $spots = $event->capacity === null ? null : max(0, $event->capacity - $reserved);
        $pctFilled = ($event->capacity !== null && $event->capacity > 0)
            ? (int) min(100, round(($reserved / $event->capacity) * 100))
            : 0;
    @endphp
    <x-card variant="quiet" tag="article">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-2">
            <div class="min-width-0">
                <p class="fw-semibold mb-1 spims-title">{{ $event->title }}</p>
                <p class="small spims-text-dim mb-0">
                    {{ $event->starts_at?->timezone(config('app.timezone'))->format('M j, H:i') }}
                    @if($event->venue)
                        &middot; {{ $event->venue }}
                    @endif
                </p>
            </div>
            <a class="btn btn-sm btn-outline-primary flex-shrink-0"
               href="{{ route('events.show', $event) }}">
                {{ __('events.view') }}
            </a>
        </div>

        @if($event->capacity !== null)
            <div class="mb-3">
                @if($spots === 0)
                    <p class="small text-danger mb-1">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        {{ __('events.seats_full') }}
                    </p>
                @else
                    <p class="small spims-text-dim mb-1">
                        {{ __('events.spots', ['open' => $spots, 'capacity' => $event->capacity]) }}
                        @if($event->waitlist_enabled ?? false)
                            &middot; {{ __('events.waitlist_available') }}
                        @endif
                    </p>
                @endif
                <x-progress :value="$pctFilled" :label="__('events.capacity_meter')" />
            </div>
        @else
            <p class="small spims-text-dim mb-3">
                {{ __('events.unlimited') }}
                @if($event->waitlist_enabled ?? false)
                    &middot; {{ __('events.waitlist_available') }}
                @endif
            </p>
        @endif

        @if($reservation)
            <x-status-badge
                :status="$reservation->status->value"
                :label="__('events.status_'.$reservation->status->value)"
            />
        @endif
    </x-card>
@endforeach
</div>
@endif
@endsection
