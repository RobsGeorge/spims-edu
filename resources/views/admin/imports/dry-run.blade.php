@extends('layouts.app')

@section('title', __('import.dry_run_title'))

@section('content')
@php
    use App\Enums\Currency;
    use App\Enums\ImportEntityType;

    $isBalance = $batch->entity_type === ImportEntityType::Balance;
    $totals = $batch->control_totals ?? null;
    $report = $batch->dry_run_report ?? null;
    $hasRun = in_array($batch->status->value, ['DRY_RUN', 'COMMITTED', 'ROLLED_BACK'], true) && $report !== null;
    $mismatch = $isBalance
        ? ($totals !== null && ! ($totals['matches'] ?? false))
        : ($totals && ($totals['declared_rows'] ?? 0) !== ($totals['computed_rows'] ?? 0));
@endphp

<nav class="mb-3 small" aria-label="breadcrumb">
    <a href="{{ route('admin.imports.index') }}" class="text-decoration-none spims-text-dim">{{ __('import.title') }}</a>
    <span class="spims-text-dim mx-1">/</span>
    <a href="{{ route('admin.imports.show', $batch) }}" class="text-decoration-none spims-text-dim">{{ __('import.batch_title') }}</a>
    <span class="spims-text-dim mx-1">/</span>
    <span class="spims-text-dim">{{ __('import.dry_run_title') }}</span>
</nav>

<x-page-header :title="__('import.dry_run_title')" :subtitle="__('import.dry_run_lead')" />

@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

@unless($hasRun)
    <x-card variant="panel">
        <x-empty-state :title="__('import.not_run_yet')" :message="__('import.not_run_yet_body')" icon="bi-play-circle">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.imports.dry-run.run', $batch) }}">
                    @csrf
                    <button class="btn btn-primary">{{ __('import.run_button') }}</button>
                </form>
            </x-slot:actions>
        </x-empty-state>
    </x-card>
@else
    @if($isBalance)
        <div class="row g-3 mb-4">
            <div class="col-3">
                <x-stat :label="__('import.report_invoices_created')" :value="$report['invoices_created'] ?? 0" />
            </div>
            <div class="col-3">
                <x-stat :label="__('import.report_wallet_credits_created')" :value="$report['wallet_credits_created'] ?? 0" />
            </div>
            <div class="col-3">
                <x-stat :label="__('import.report_noop')" :value="$report['noop'] ?? 0" />
            </div>
            <div class="col-3">
                <x-stat :label="__('import.report_skip')" :value="$report['skip'] ?? 0" />
            </div>
        </div>

        <x-card variant="quiet" class="mb-4">
            <h2 class="h6 page-title mb-1">{{ __('import.control_totals_title') }}</h2>
            <p class="small spims-text-dim mb-3">{{ __('import.balance_gate_help') }}</p>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('import.col_currency') }}</th>
                            <th class="text-end">{{ __('import.control_totals_declared') }}</th>
                            <th class="text-end">{{ __('import.control_totals_computed') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(\App\Services\Import\ImportBalanceFields::SUPPORTED_CURRENCIES as $code)
                            @php
                                $currencyEnum = Currency::from($code);
                                $declaredOwed = (int) ($totals['declared'][$code]['owed_minor'] ?? 0);
                                $declaredCredit = (int) ($totals['declared'][$code]['credit_minor'] ?? 0);
                                $computedOwed = (int) ($totals['computed'][$code]['owed_minor'] ?? 0);
                                $computedCredit = (int) ($totals['computed'][$code]['credit_minor'] ?? 0);
                                $rowMatches = $declaredOwed === $computedOwed && $declaredCredit === $computedCredit;
                            @endphp
                            <tr>
                                <td>{{ $code }} — {{ __('import.col_owed') }}</td>
                                <td class="text-end"><x-money :minor="$declaredOwed" :currency="$currencyEnum" /></td>
                                <td class="text-end"><x-money :minor="$computedOwed" :currency="$currencyEnum" /></td>
                                <td>
                                    @if($rowMatches)
                                        <span class="spims-status-badge spims-status-success">{{ __('import.control_totals_match') }}</span>
                                    @else
                                        <span class="spims-status-badge spims-status-danger">{{ __('import.control_totals_mismatch') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>{{ $code }} — {{ __('import.col_credit') }}</td>
                                <td class="text-end"><x-money :minor="$declaredCredit" :currency="$currencyEnum" /></td>
                                <td class="text-end"><x-money :minor="$computedCredit" :currency="$currencyEnum" /></td>
                                <td>
                                    @if($rowMatches)
                                        <span class="spims-status-badge spims-status-success">{{ __('import.control_totals_match') }}</span>
                                    @else
                                        <span class="spims-status-badge spims-status-danger">{{ __('import.control_totals_mismatch') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        @if($batch->status->value === 'DRY_RUN')
            <x-card variant="panel">
                @if($mismatch)
                    <div class="alert alert-danger mb-3" role="alert">
                        {{ __('import.finance_mismatch_refused') }}
                    </div>
                @endif
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('admin.imports.dry-run.run', $batch) }}">
                        @csrf
                        <button class="btn btn-outline-secondary">{{ __('import.rerun_button') }}</button>
                    </form>
                    @unless($mismatch)
                        <form method="POST" action="{{ route('admin.imports.commit', $batch) }}" onsubmit="return confirm('{{ __('import.commit_confirm') }}')">
                            @csrf
                            <button class="btn btn-primary">{{ __('import.commit_button') }}</button>
                        </form>
                    @endunless
                </div>
            </x-card>
        @elseif($batch->status->value === 'COMMITTED')
            <a href="{{ route('admin.imports.show', $batch) }}" class="btn btn-outline-primary">{{ __('import.batch_title') }}</a>
        @endif
    @else
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <x-stat :label="__('import.report_create')" :value="$report['create'] ?? 0" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat :label="__('import.report_link')" :value="$report['link'] ?? 0" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat :label="__('import.report_skip')" :value="$report['skip'] ?? 0" />
            </div>
            <div class="col-6 col-lg-3">
                <x-stat :label="__('import.report_queued')" :value="$report['queued'] ?? 0" icon="warning" />
            </div>
        </div>

        @if(($report['queued'] ?? 0) > 0)
            <x-card variant="quiet" class="mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="small">{{ __('import.report_queued_body', ['count' => $report['queued']]) }}</span>
                    <a href="{{ route('admin.imports.merges') }}" class="btn btn-outline-primary btn-sm">{{ __('import.review_merges') }}</a>
                </div>
            </x-card>
        @endif

        <x-card variant="quiet" class="mb-4">
            <h2 class="h6 page-title mb-3">{{ __('import.control_totals_title') }}</h2>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th></th><th class="text-end">{{ __('import.control_totals_declared') }}</th><th class="text-end">{{ __('import.control_totals_computed') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ __('import.col_rows') }}</td>
                            <td class="text-end">{{ number_format($totals['declared_rows'] ?? 0) }}</td>
                            <td class="text-end">{{ number_format($totals['computed_rows'] ?? 0) }}</td>
                            <td>
                                @if($mismatch)
                                    <span class="spims-status-badge spims-status-warning">{{ __('import.control_totals_differ', ['count' => abs(($totals['declared_rows'] ?? 0) - ($totals['computed_rows'] ?? 0))]) }}</span>
                                @else
                                    <span class="spims-status-badge spims-status-success">{{ __('import.control_totals_match') }}</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-card>

        @if($batch->status->value === 'DRY_RUN')
            <x-card variant="panel" x-data="{ ack: {{ $mismatch ? 'false' : 'true' }} }">
                @if($mismatch)
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="ack" x-model="ack">
                        <label class="form-check-label small" for="ack">{{ __('import.ack_label') }}</label>
                    </div>
                @endif
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('admin.imports.dry-run.run', $batch) }}">
                        @csrf
                        <button class="btn btn-outline-secondary">{{ __('import.rerun_button') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.imports.commit', $batch) }}" onsubmit="return confirm('{{ __('import.commit_confirm') }}')">
                        @csrf
                        <button class="btn btn-primary" :disabled="!ack">{{ __('import.commit_button') }}</button>
                    </form>
                </div>
            </x-card>
        @elseif($batch->status->value === 'COMMITTED')
            <a href="{{ route('admin.imports.show', $batch) }}" class="btn btn-outline-primary">{{ __('import.batch_title') }}</a>
        @endif
    @endif
@endunless
@endsection
