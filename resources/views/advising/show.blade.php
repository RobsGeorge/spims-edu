@extends('layouts.app')
@section('title', __('advising.advisee'))
@section('content')
<x-page-header
    :title="$student->first_name.' '.$student->last_name"
    :subtitle="__('advising.advisee')"
>
    <x-slot:actions>
        <a class="btn btn-outline-secondary" href="{{ route('advising.index') }}">{{ __('advising.back_to_roster') }}</a>
    </x-slot:actions>
</x-page-header>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<p class="spims-text-dim">{{ $student->email }}</p>

@if($programs->isNotEmpty())
<x-card variant="panel" tag="section" class="mb-3">
    <h2 class="h5 spims-title">{{ __('advising.programs') }}</h2>
    @foreach($programs as $sp)
        <div class="py-2 border-bottom border-opacity-25">
            <a href="{{ route('enrollments.audit', $sp) }}">{{ __('enrollment.degree_audit') }} — {{ $sp->program->code }}</a>
            <div class="small spims-text-dim">{{ __('advising.what_if_hint') }}</div>
        </div>
    @endforeach
</x-card>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <x-card variant="panel" tag="section" class="h-100">
            <h2 class="h5 spims-title">{{ __('advising.holds') }}</h2>
            @forelse($holds as $hold)
                <div class="py-2 border-bottom border-opacity-25 d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold"><x-badge :value="$hold->kind" /></div>
                        <div>{{ $hold->reason }}</div>
                        <div class="small spims-text-dim">
                            @if($hold->isActive())
                                {{ __('advising.hold_active') }}
                            @else
                                {{ __('advising.hold_inactive') }}
                            @endif
                        </div>
                    </div>
                    @if($canHold && $hold->isActive())
                        <form method="POST" action="{{ route('advising.holds.release', $hold) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">{{ __('advising.release_hold') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="spims-text-dim mb-0">{{ __('advising.no_holds') }}</p>
            @endforelse
        </x-card>
    </div>
    @if($canHold)
    <div class="col-lg-6">
        <x-card variant="panel" tag="section" class="h-100">
            <h2 class="h5 spims-title">{{ __('advising.place_hold') }}</h2>
            <form method="POST" action="{{ route('advising.holds.store', $student) }}" class="row g-2">
                @csrf
                <div class="col-12">
                    <label class="form-label" for="hold-kind">{{ __('advising.hold_kind') }}</label>
                    <select id="hold-kind" name="kind" class="form-select" required>
                        @foreach($holdKinds as $kind)
                            @php $kindVal = $kind->value; @endphp
                            <option value="{{ $kindVal }}">{{ __('advising_hold_kind.'.$kindVal) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="hold-reason">{{ __('advising.reason') }}</label>
                    <textarea id="hold-reason" name="reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-outline-danger">{{ __('advising.place_hold') }}</button>
                </div>
            </form>
        </x-card>
    </div>
    @endif
</div>

@if($notes->isNotEmpty())
<x-card variant="panel" tag="section">
    <h2 class="h5 spims-title">{{ __('advising.notes') }}</h2>
    @if($offering)
        <p class="small spims-text-dim">{{ $offering->course->code ?? '' }}</p>
    @endif
    @foreach($notes as $note)
        <div class="py-2 border-bottom border-opacity-25">
            <div>{{ $note->body }}</div>
            <div class="small spims-text-dim">{{ $note->author?->first_name }} {{ $note->author?->last_name }}</div>
        </div>
    @endforeach
</x-card>
@endif
@endsection
