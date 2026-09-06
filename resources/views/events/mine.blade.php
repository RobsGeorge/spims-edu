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
    <article class="border rounded-3 p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
            <div>
                <h2 class="h6 mb-1">{{ $event?->title ?? __('events.not_found') }}</h2>
                <div class="small text-muted-theme mb-2">
                    @if($event)
                        {{ $event->starts_at?->timezone(config('app.timezone'))->format('M j, H:i') }}
                        @if($event->venue)
                            · {{ $event->venue }}
                        @endif
                    @endif
                </div>
                <x-status-badge :status="$reservation->status->value" :label="__('events.status_'.$reservation->status->value)" />
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($event)
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('events.show', $event) }}">{{ __('events.view') }}</a>
                    @if($reservation->isOpen())
                        <form method="POST" action="{{ route('events.cancel', $event) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">{{ __('events.cancel_reservation') }}</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
        @if(!empty($qrById[$reservation->id]))
            @include('events.partials.check-in-ticket', ['payload' => $qrById[$reservation->id]])
        @endif
    </article>
@empty
    <x-empty-state :title="__('events.mine_empty')" icon="bi-calendar-check" />
@endforelse
@endsection
