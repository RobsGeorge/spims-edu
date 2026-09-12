@extends('layouts.app')

@section('title', __('import.activation_title'))

@section('content')
<nav class="mb-3 small" aria-label="breadcrumb">
    <a href="{{ route('admin.imports.index') }}" class="text-decoration-none spims-text-dim">{{ __('import.title') }}</a>
    <span class="spims-text-dim mx-1">/</span>
    <span class="spims-text-dim">{{ __('import.activation_title') }}</span>
</nav>

<x-page-header :title="__('import.activation_title')" :subtitle="__('import.activation_subtitle')" />

@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-stat :label="__('import.stat_eligible')" :value="$stats['eligible']" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat :label="__('import.stat_invited')" :value="$stats['invited']" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat :label="__('import.stat_claimed')" :value="$stats['claimed']" icon="success" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat :label="__('import.stat_bounced')" :value="$stats['bounced']" />
    </div>
</div>

@if($claims->isEmpty())
    <x-empty-state :title="__('import.claims_empty_title')" :message="__('import.claims_empty_message')" icon="bi-envelope" />
@else
    <div
        x-data="{
            tab: @js($tab),
            selected: [],
            toggleAll(ids, checked) {
                if (checked) {
                    ids.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
                } else {
                    this.selected = this.selected.filter(id => !ids.includes(id));
                }
            }
        }"
    >
        <x-tabs id="activation-tabs" :items="[
            ['key' => 'all', 'label' => __('import.tab_all')],
            ['key' => 'not_invited', 'label' => __('import.tab_not_invited')],
            ['key' => 'invited', 'label' => __('import.tab_invited')],
            ['key' => 'claimed', 'label' => __('import.tab_claimed')],
            ['key' => 'bounced', 'label' => __('import.tab_bounced')],
        ]">
            <x-card variant="panel" class="mt-3">
                <form method="POST" action="{{ route('admin.imports.activation.send') }}" id="activation-send-form">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="claim_ids[]" :value="id">
                    </template>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="selected.length === 0">
                            <span x-text="'{{ __('import.send_selected_button', ['count' => ':n']) }}'.replace(':n', selected.length)"></span>
                        </button>
                        <span class="small spims-text-dim">{{ __('import.send_rate_note', ['size' => config('import.claim_send_chunk_size')]) }}</span>
                    </div>
                </form>

                <div class="table-responsive spims-table-wrap">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>
                                    <input
                                        type="checkbox"
                                        aria-label="{{ __('import.select_all') }}"
                                        x-on:change="toggleAll(@js($claims->filter(fn ($c) => $c->status->value === 'QUEUED')->pluck('id')->values()), $event.target.checked)"
                                    >
                                </th>
                                <th>{{ __('import.col_student') }}</th>
                                <th>{{ __('import.col_email') }}</th>
                                <th>{{ __('import.col_batch') }}</th>
                                <th>{{ __('import.col_claim_status') }}</th>
                                <th>{{ __('import.col_invited_at') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($claims as $claim)
                            @php
                                $rowTab = match($claim->status->value) {
                                    'QUEUED' => 'not_invited',
                                    'SENT' => 'invited',
                                    'CLAIMED' => 'claimed',
                                    'BOUNCED' => 'bounced',
                                    default => null,
                                };
                            @endphp
                            <tr x-show="tab === 'all' || tab === {{ $rowTab ? "'".$rowTab."'" : "'__none__'" }}" x-cloak>
                                <td>
                                    @if($claim->status->value === 'QUEUED' || $claim->status->value === 'BOUNCED')
                                        <input type="checkbox" value="{{ $claim->id }}" x-model="selected" aria-label="{{ __('import.select_all') }}">
                                    @endif
                                </td>
                                <td>{{ $claim->user?->displayName() ?? '—' }}</td>
                                <td class="small">{{ $claim->user?->email ?? '—' }}</td>
                                <td class="small spims-text-dim">{{ $claim->batch?->file_name ?? '—' }}</td>
                                <td><x-status-badge :status="strtolower($claim->status->value)" :label="__('import.claim_status_'.$claim->status->value)" /></td>
                                <td class="small spims-text-dim">{{ $claim->invited_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @if($claim->status->value === 'SENT')
                                        <form method="POST" action="{{ route('admin.imports.activation.bounce', $claim) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('import.mark_bounced_button') }}</button>
                                        </form>
                                    @elseif($claim->status->value === 'BOUNCED')
                                        <form method="POST" action="{{ route('admin.imports.activation.correct-email', $claim) }}" class="d-flex gap-1">
                                            @csrf
                                            <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', $claim->user?->email) }}" placeholder="{{ __('import.correct_email_label') }}" required>
                                            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">{{ __('import.correct_email_button') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </x-tabs>
    </div>
@endif
@endsection
