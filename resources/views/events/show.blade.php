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

<div class="app-card p-3 mb-3">
    @if($event->description)
        <p class="mb-3">{{ $event->description }}</p>
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
            @else
                {{ __('events.spots', ['open' => max(0, $event->capacity - $reservedCount), 'capacity' => $event->capacity]) }}
            @endif
        </dd>
    </dl>
</div>

@if($reservation)
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <x-status-badge :status="$reservation->status->value" :label="__('events.status_'.$reservation->status->value)" />
        <form method="POST" action="{{ route('events.cancel', $event) }}">
            @csrf
            <button class="btn btn-sm btn-outline-danger">{{ __('events.cancel_reservation') }}</button>
        </form>
    </div>
    @if($qrPayload)
        @include('events.partials.check-in-ticket', ['payload' => $qrPayload])
    @endif
@elseif($eligible)
    <form method="POST" action="{{ route('events.reserve', $event) }}">
        @csrf
        <button class="btn btn-primary">{{ __('events.reserve') }}</button>
    </form>
@else
    <p class="text-muted-theme mb-0">{{ __('events.ineligible_hint') }}</p>
@endif
@endsection
