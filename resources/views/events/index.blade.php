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

@forelse($events as $event)
    @php
        $reservation = $mine[$event->id] ?? null;
        $spots = $event->capacity === null ? null : max(0, $event->capacity - (int) $event->reserved_count);
    @endphp
    <article class="border rounded-3 p-3 mb-2">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
            <div>
                <h2 class="h6 mb-1">{{ $event->title }}</h2>
                <div class="small text-muted-theme">
                    {{ $event->starts_at?->timezone(config('app.timezone'))->format('M j, H:i') }}
                    @if($event->venue)
                        · {{ $event->venue }}
                    @endif
                    ·
                    @if($event->capacity === null)
                        {{ __('events.unlimited') }}
                    @else
                        {{ __('events.spots', ['open' => $spots, 'capacity' => $event->capacity]) }}
                    @endif
                </div>
                @if($reservation)
                    <x-status-badge :status="$reservation->status->value" :label="__('events.status_'.$reservation->status->value)" />
                @endif
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('events.show', $event) }}">{{ __('events.view') }}</a>
        </div>
    </article>
@empty
    <x-empty-state :title="__('events.catalog_empty')" icon="bi-calendar-event" />
@endforelse
@endsection
