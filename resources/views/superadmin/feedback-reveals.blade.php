@extends('layouts.app')

@section('title', __('staff.surveys.reveals_title'))

@section('content')
<div class="hub-page animate-in" style="max-width:800px;margin:0 auto;">
    <div class="mb-3">
        <a href="{{ route('superadmin.index') }}" class="text-decoration-none text-muted-theme">
            @include('partials.superadmin-entry-tag', ['class' => 'me-1']) {{ __('superadmin.title') }}
        </a>
        <span class="text-muted-theme mx-1">·</span>
        <span class="text-muted-theme">{{ __('staff.surveys.reveals_title') }}</span>
    </div>
    <h1 class="page-title">{{ __('staff.surveys.reveals_title') }}</h1>
    <p class="text-muted-theme mb-2">{{ __('staff.surveys.reveals_sub') }}</p>
    <p class="small text-muted-theme mb-4">{{ __('status.reveals_help') }}</p>
    @include('partials.status-entrance-banner', ['caption' => __('status.entrance_from_reveals')])

    @forelse($requests as $reveal)
        <div class="app-card card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <strong>{{ $reveal->submission?->survey?->title }}</strong>
                        <div class="small text-muted-theme">
                            {{ __('staff.surveys.requested_by') }}: {{ $reveal->requester?->email }}
                            · {{ $reveal->reason }}
                        </div>
                    </div>
                    <x-status-badge :status="$reveal->status->value" :label="__('staff.surveys.reveal_status_'.$reveal->status->value)" />
                </div>
                @if($reveal->isPending())
                    <p class="form-text mt-2 mb-2">{{ __('superadmin.reveals_decide_help') }}</p>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('superadmin.feedback-reveals.decide', $reveal) }}"
                              onsubmit="return confirm(@json(__('superadmin.reveals_approve_confirm')));">
                            @csrf
                            <input type="hidden" name="approve" value="1">
                            <button class="btn btn-sm btn-primary">{{ __('staff.surveys.approve') }}</button>
                        </form>
                        <form method="POST" action="{{ route('superadmin.feedback-reveals.decide', $reveal) }}"
                              onsubmit="return confirm(@json(__('superadmin.reveals_deny_confirm')));">
                            @csrf
                            <input type="hidden" name="approve" value="0">
                            <button class="btn btn-sm btn-outline-danger">{{ __('staff.surveys.deny') }}</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <x-empty-state :title="__('staff.surveys.no_reveals')" icon="bi-shield-lock" />
        <p class="form-text">{{ __('superadmin.reveals_empty_help') }}</p>
    @endforelse
</div>
@endsection
