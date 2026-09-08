@extends('layouts.app')
@section('title', __('staff.surveys.reveals_title'))
@section('content')
<x-page-header
    :title="__('staff.surveys.reveals_title')"
    :subtitle="__('staff.surveys.reveals_sub')"
    :eyebrow="__('superadmin.role')"
/>

@forelse($requests as $reveal)
    <div class="border rounded-3 p-3 mb-2">
        <div class="d-flex justify-content-between gap-2">
            <div>
                <strong>{{ $reveal->submission?->survey?->title }}</strong>
                <div class="small spims-text-dim">
                    {{ __('staff.surveys.requested_by') }}: {{ $reveal->requester?->email }}
                    · {{ $reveal->reason }}
                </div>
            </div>
            <x-status-badge :status="$reveal->status->value" :label="__('staff.surveys.reveal_status_'.$reveal->status->value)" />
        </div>
        @if($reveal->isPending())
            <div class="d-flex gap-2 mt-2">
                <form method="POST" action="{{ route('superadmin.feedback-reveals.decide', $reveal) }}">
                    @csrf
                    <input type="hidden" name="approve" value="1">
                    <button class="btn btn-sm btn-primary">{{ __('staff.surveys.approve') }}</button>
                </form>
                <form method="POST" action="{{ route('superadmin.feedback-reveals.decide', $reveal) }}">
                    @csrf
                    <input type="hidden" name="approve" value="0">
                    <button class="btn btn-sm btn-outline-danger">{{ __('staff.surveys.deny') }}</button>
                </form>
            </div>
        @endif
    </div>
@empty
    <x-empty-state :title="__('staff.surveys.no_reveals')" icon="bi-shield-lock" />
@endforelse
@endsection
