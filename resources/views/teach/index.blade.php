@extends('layouts.app')
@section('title', __('teach.title'))
@section('content')
<x-page-header :title="__('teach.title')" :subtitle="__('teach.subtitle')" />

@if($offerings->isEmpty())
    <x-empty-state
        :title="__('teach.empty_title')"
        :message="__('teach.empty_message')"
        icon="bi-easel2"
    />
@else
    <div class="row g-3">
        @foreach($offerings as $offering)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('teach.show', $offering) }}" class="teach-offering-card d-block h-100 text-decoration-none">
                    <div class="p-3">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <span class="fw-semibold text-body">{{ $offering->course->code }}</span>
                            <x-status-badge :status="$offering->status->value" :label="$offering->status->value" />
                        </div>
                        <h2 class="h6 spims-title mb-1">{{ $offering->course->title }}</h2>
                        <p class="small text-muted-theme mb-0">
                            {{ $offering->mode->value }}
                            @if($offering->semester)
                                · {{ $offering->semester->name }}
                            @endif
                        </p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endif
@endsection
