@extends('layouts.app')
@section('title', $offering->course->code)
@section('content')
<x-page-header
    :title="$offering->course->code.' — '.$offering->course->title"
    :subtitle="$offering->mode->value.' · '.$offering->status->value"
>
    <x-slot:actions>
        <a href="{{ route('admin.offerings.edit', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('ui.edit') }}</a>
        @if($canViewWaitlist)
            <a href="{{ route('admin.enrollments.waitlist', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('enrollment.waitlist') }}</a>
        @endif
        <a href="{{ route('offerings.preview', $offering) }}" class="btn btn-outline-secondary btn-sm">{{ __('offerings.public_preview') }}</a>
        <form method="POST" action="{{ route('offerings.preview.student', $offering) }}" class="d-inline">
            @csrf
            <button class="btn btn-outline-secondary btn-sm">{{ __('offerings.view_as_student') }}</button>
        </form>
        <a href="{{ route('teach.show', $offering) }}" class="btn btn-outline-primary btn-sm">{{ __('teach.workspace') }}</a>
    </x-slot:actions>
</x-page-header>
@include('partials.offering-workspace-tabs', ['offering' => $offering, 'active' => 'content', 'prefix' => 'admin'])
@if(session('status'))<div class="alert alert-success mt-3">{{ session('status') }}</div>@endif

<div class="row g-3">
    <div class="col-md-6">
        <x-card variant="panel" class="h-100">
            <h2 class="h6">{{ __('offerings.assign_staff') }}</h2>
            <form method="POST" action="{{ route('admin.offerings.staff', $offering) }}" class="row g-2">
                @csrf
                <div class="col-8">
                    <select name="user_id" class="form-select" required>
                        @foreach($instructors as $user)
                            <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4">
                    <select name="role" class="form-select" required>
                        @foreach($staffRoles as $role)@php $roleVal = $role->value; @endphp<option value="{{ $roleVal }}">{{ $roleVal }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12"><button class="btn btn-primary btn-sm">{{ __('ui.save') }}</button></div>
            </form>
            <ul class="mt-3 mb-0">
                @foreach($offering->staff as $staff)
                    <li class="d-flex justify-content-between align-items-center gap-2">
                        <span>{{ $staff->user->first_name }} {{ $staff->user->last_name }} (<x-badge :value="$staff->role" />)</span>
                        <form method="POST" action="{{ route('admin.offerings.unstaff', [$offering, $staff]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('offerings.remove_staff') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
    <div class="col-md-6">
        <x-card variant="panel" class="h-100">
            <h2 class="h6">{{ __('offerings.pricing') }}</h2>
            <p class="small spims-text-dim">{{ __('offerings.resolved') }}: USD {{ $offering->resolvedPriceUsd() }} / EGP {{ $offering->resolvedPriceEgp() }}</p>
            <form method="POST" action="{{ route('admin.offerings.pricing', $offering) }}" class="row g-2">
                @csrf
                <div class="col-6"><input type="number" name="price_usd_override" class="form-control" placeholder="USD minor" value="{{ $offering->price_usd_override }}"></div>
                <div class="col-6"><input type="number" name="price_egp_override" class="form-control" placeholder="EGP minor" value="{{ $offering->price_egp_override }}"></div>
                <div class="col-12"><button class="btn btn-outline-primary btn-sm">{{ __('offerings.save_pricing') }}</button></div>
            </form>
        </x-card>
    </div>
</div>

<x-card variant="panel" class="mt-3" id="edit">
    <h2 class="h6">{{ __('offerings.update_offering') }}</h2>
    <form method="POST" action="{{ route('admin.offerings.update', $offering) }}" class="row g-2">
        @csrf
        @method('PUT')
        @if($offering->mode->value === 'COHORT')
        <div class="col-md-3">
            <label class="form-label">{{ __('offerings.semester') }}</label>
            <select name="semester_id" class="form-select">
                <option value="">—</option>
                @foreach($semesters as $semester)
                    <option value="{{ $semester->id }}" @selected($offering->semester_id === $semester->id)>{{ $semester->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-md-2">
            <label class="form-label">{{ __('offerings.seat_capacity') }}</label>
            <input type="number" name="seat_capacity" class="form-control" value="{{ $offering->seat_capacity }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('offerings.start_date') }}</label>
            <input type="date" name="start_date" class="form-control" value="{{ $offering->start_date?->toDateString() }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('offerings.end_date') }}</label>
            <input type="date" name="end_date" class="form-control" value="{{ $offering->end_date?->toDateString() }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('offerings.status') }}</label>
            <select name="status" class="form-select" required>
                @foreach($statuses as $status)
                    @php $statusVal = $status->value; @endphp
                    <option value="{{ $statusVal }}" @selected($offering->status->value === $statusVal)>{{ $statusVal }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12"><button class="btn btn-primary btn-sm">{{ __('ui.save_changes') }}</button></div>
    </form>
</x-card>

<x-card variant="panel" class="mt-3">
    @include('offerings.partials.add-week-form', ['offering' => $offering])

    @include('offerings.partials.week-content-builder', ['offering' => $offering, 'contentTypes' => $contentTypes])
</x-card>
@endsection
