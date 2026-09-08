@extends('layouts.app')
@section('title', __('attendance.history'))
@section('content')
<x-page-header :title="__('attendance.history')">
    <x-slot:actions>
        <a href="{{ route('attendance.check-in') }}" class="btn btn-primary btn-sm">{{ __('attendance.check_in') }}</a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if($percents !== [])
    <div class="row g-3 mb-4">
        @foreach($percents as $row)
            <div class="col-12 col-md-4">
                <div class="app-card p-3">
                    <div class="small text-muted-theme">{{ $row['offering']->course->code }}</div>
                    <strong>{{ __('attendance.own_percent') }}: {{ $row['percent'] === null ? '—' : $row['percent'].'%' }}</strong>
                    @if($row['policy'])
                        <div class="small">{{ __('attendance.threshold') }}: {{ $row['policy']->min_percentage }}%</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

@forelse($entries as $entry)
    <div class="border rounded-3 p-3 mb-2">
        <strong>{{ $entry->session?->title }}</strong>
        <div class="small text-muted-theme">
            {{ $entry->session?->offering?->course?->code }} · {{ $entry->session?->scheduled_start }}
        </div>
        <x-status-badge :status="$entry->status->value" :label="__('attendance.status_'.$entry->status->value)" />
    </div>
@empty
    <x-empty-state :title="__('attendance.history_empty')" icon="bi-calendar-check" />
@endforelse
@endsection
