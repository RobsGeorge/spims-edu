@extends('layouts.app')

@section('title', __('import.merges_title'))

@section('content')
<nav class="mb-3 small" aria-label="breadcrumb">
    <a href="{{ route('admin.imports.index') }}" class="text-decoration-none spims-text-dim">{{ __('import.title') }}</a>
    <span class="spims-text-dim mx-1">/</span>
    <span class="spims-text-dim">{{ __('import.merges_title') }}</span>
</nav>

<x-page-header :title="__('import.merges_title')" :subtitle="__('import.merges_subtitle', ['count' => $pendingCount])" />

@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

@if($candidates->isEmpty())
    <x-card variant="panel">
        <x-empty-state :title="__('import.merges_empty_title')" :message="__('import.merges_empty_message')" icon="bi-people" />
    </x-card>
@else
    @foreach($candidates as $candidate)
        @php
            $row = $incoming[$candidate->id] ?? [];
            $candidateUser = $candidate->candidateUser;
            $incomingName = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) ?: '—';
        @endphp
        <x-card variant="panel" class="mb-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <span class="small spims-text-dim">{{ __('import.merges_from', ['source' => $candidate->source?->name ?? '—']) }}</span>
                @if(!empty($candidate->matched_on))
                    <span class="small spims-text-dim">
                        {{ __('import.merges_matched_on', ['fields' => collect($candidate->matched_on)->map(fn ($f) => __('import.match_signal_'.$f))->join(', ')]) }}
                        &middot; {{ __('import.merges_score', ['score' => number_format($candidate->score * 100, 0)]) }}
                    </span>
                @else
                    <span class="small spims-text-dim">{{ __('import.merges_no_signal') }}</span>
                @endif
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-start gap-2">
                        <x-avatar :name="$incomingName !== '—' ? $incomingName : '?'" />
                        <div>
                            <div class="small spims-text-dim">{{ __('import.merges_incoming_label') }}</div>
                            <strong>{{ $incomingName }}</strong>
                            <div class="small">{{ $row['email'] ?? __('import.merges_no_email') }}</div>
                            <div class="small spims-text-dim">{{ __('import.merges_dob') }}: {{ $row['date_of_birth'] ?? '—' }}</div>
                            <div class="small spims-text-dim">{{ __('import.merges_legacy_id') }}: {{ $candidate->legacy_id }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    @if($candidateUser)
                        <div class="d-flex align-items-start gap-2">
                            <x-avatar :name="$candidateUser->displayName()" />
                            <div>
                                <div class="small spims-text-dim">{{ __('import.merges_candidate_label') }}</div>
                                <strong>{{ $candidateUser->displayName() }}</strong>
                                <div class="small">{{ $candidateUser->email }}</div>
                                <div class="small spims-text-dim">{{ __('import.merges_dob') }}: {{ $candidateUser->date_of_birth?->format('Y-m-d') ?? '—' }}</div>
                            </div>
                        </div>
                    @else
                        <div class="small spims-text-dim">{{ __('import.merges_no_candidate') }}</div>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if($candidateUser)
                    <form method="POST" action="{{ route('admin.imports.merges.resolve', $candidate) }}" onsubmit="return confirm('{{ __('import.merges_confirm_merge') }}')">
                        @csrf
                        <input type="hidden" name="decision" value="merge">
                        <button class="btn btn-primary btn-sm">{{ __('import.merges_action_merge') }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.imports.merges.resolve', $candidate) }}" onsubmit="return confirm('{{ __('import.merges_confirm_reject') }}')">
                    @csrf
                    <input type="hidden" name="decision" value="reject">
                    <button class="btn btn-outline-secondary btn-sm">{{ __('import.merges_action_reject') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.imports.merges.resolve', $candidate) }}">
                    @csrf
                    <input type="hidden" name="decision" value="skip">
                    <button class="btn btn-outline-secondary btn-sm">{{ __('import.merges_action_skip') }}</button>
                </form>
            </div>
        </x-card>
    @endforeach

    <div class="mt-3">{{ $candidates->links() }}</div>
@endif
@endsection
