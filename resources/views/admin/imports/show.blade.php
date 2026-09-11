@extends('layouts.app')

@section('title', __('import.batch_title'))

@section('content')
<nav class="mb-3 small" aria-label="breadcrumb">
    <a href="{{ route('admin.imports.index') }}" class="text-decoration-none spims-text-dim">{{ __('import.title') }}</a>
    <span class="spims-text-dim mx-1">/</span>
    <span class="spims-text-dim">{{ __('import.batch_title') }}</span>
</nav>

<x-page-header :title="__('import.batch_title')" :subtitle="$batch->file_name">
    <x-slot:actions>
        <x-status-badge :status="strtolower($batch->status->value)" :label="__('import.status_'.$batch->status->value)" />
    </x-slot:actions>
</x-page-header>

@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if(session('warning'))<div class="alert alert-warning" role="status">{{ session('warning') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

<x-card variant="quiet" class="mb-4">
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <span class="small spims-text-dim d-block">{{ __('import.source_label') }}</span>
            <strong>{{ $batch->source->name }}</strong>
        </div>
        <div class="col-6 col-md-3">
            <span class="small spims-text-dim d-block">{{ __('import.population_label') }}</span>
            <strong>{{ $batch->population ? __('import.population_'.$batch->population->value) : '—' }}</strong>
        </div>
        <div class="col-6 col-md-3">
            <span class="small spims-text-dim d-block">{{ __('import.created_by_label') }}</span>
            <strong>{{ $batch->createdBy?->displayName() ?? '—' }}</strong>
        </div>
        <div class="col-6 col-md-3">
            <span class="small spims-text-dim d-block">{{ __('import.col_created') }}</span>
            <strong>{{ $batch->created_at->format('Y-m-d H:i') }}</strong>
        </div>
    </div>
</x-card>

<div class="row g-3 mb-4">
    <div class="col-4">
        <x-stat :label="__('import.stat_valid')" :value="$validRows" />
    </div>
    <div class="col-4">
        <x-stat :label="__('import.stat_warnings')" :value="$warnRows" />
    </div>
    <div class="col-4">
        <x-stat :label="__('import.stat_errors')" :value="$errorRows" />
    </div>
</div>

<x-card variant="panel" class="mb-4">
    <h2 class="h6 page-title mb-3">{{ __('import.issues_title') }}</h2>
    @if($issues->isEmpty())
        <p class="small spims-text-dim mb-0">{{ __('import.issues_none') }}</p>
    @else
        @foreach($issues as $code => $group)
            <details class="mb-2">
                <summary style="cursor:pointer">
                    <span class="spims-status-badge spims-status-{{ $group->first()['level'] === 'error' ? 'danger' : 'warning' }}">
                        {{ $group->first()['level'] === 'error' ? __('import.level_error') : __('import.level_warning') }}
                    </span>
                    <code class="small">{{ $code }}</code>
                    <span class="small spims-text-dim">&middot; {{ $group->count() }}</span>
                </summary>
                <div class="ps-4 pt-2">
                    <ul class="small mb-0">
                        @foreach($group->take(20) as $item)
                            <li>{{ __('import.error_code.'.$code, $item['params'] ?? []) }} <span class="spims-text-dim">({{ __('import.issue_rows', ['rows' => $item['row']]) }})</span></li>
                        @endforeach
                        @if($group->count() > 20)
                            <li class="spims-text-dim">&hellip; {{ $group->count() - 20 }} {{ __('import.stat_errors') }}</li>
                        @endif
                    </ul>
                </div>
            </details>
        @endforeach
    @endif
</x-card>

<x-card variant="panel">
    @switch($batch->status->value)
        @case('DRAFT')
        @case('MAPPED')
            <h2 class="h6 page-title mb-2">{{ __('import.next_step_MAPPED_title') }}</h2>
            <p class="small spims-text-dim mb-3">{{ __('import.next_step_MAPPED_body') }}</p>
            <a href="{{ route('admin.imports.map', $batch) }}" class="btn btn-primary">{{ __('import.continue_mapping') }}</a>
            @break

        @case('VALIDATED')
            <h2 class="h6 page-title mb-2">{{ __('import.next_step_VALIDATED_title') }}</h2>
            <p class="small spims-text-dim mb-3">{{ __('import.next_step_VALIDATED_body') }}</p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.imports.map', $batch) }}" class="btn btn-outline-secondary">{{ __('import.edit_mapping') }}</a>
                <a href="{{ route('admin.imports.dry-run', $batch) }}" class="btn btn-primary">{{ __('import.run_dry_run') }}</a>
            </div>
            @break

        @case('DRY_RUN')
            <h2 class="h6 page-title mb-2">{{ __('import.next_step_DRY_RUN_title') }}</h2>
            <p class="small spims-text-dim mb-3">{{ __('import.next_step_DRY_RUN_body') }}</p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.imports.map', $batch) }}" class="btn btn-outline-secondary">{{ __('import.edit_mapping') }}</a>
                <a href="{{ route('admin.imports.dry-run', $batch) }}" class="btn btn-primary">{{ __('import.view_dry_run') }}</a>
            </div>
            @break

        @case('COMMITTED')
            <h2 class="h6 page-title mb-2">{{ __('import.next_step_COMMITTED_title') }}</h2>
            <p class="small spims-text-dim mb-3">
                {{ __('import.committed_by', ['name' => $batch->committedBy?->displayName() ?? '—', 'date' => $batch->committed_at?->format('Y-m-d H:i')]) }}
            </p>

            @if(session('rollback_blocked'))
                <div class="alert alert-warning">
                    <h3 class="h6">{{ __('import.rollback_blocked_title') }}</h3>
                    <ul class="small mb-0">
                        @foreach(session('rollback_blocked') as $blocked)
                            <li>{{ __('import.rollback_blocked_row', ['row' => $blocked['row']]) }} — {{ $blocked['reason'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($batch->isSealed())
                <p class="small spims-text-dim mb-0">{{ __('import.rollback_sealed', ['date' => $batch->sealed_at->format('Y-m-d')]) }}</p>
            @else
                <form method="POST" action="{{ route('admin.imports.rollback', $batch) }}" onsubmit="return confirm('{{ __('import.rollback_confirm') }}')">
                    @csrf
                    <button class="btn btn-outline-danger">{{ __('import.rollback_button') }}</button>
                </form>
            @endif
            @break

        @case('ROLLED_BACK')
            <h2 class="h6 page-title mb-2">{{ __('import.rolled_back_title') }}</h2>
            <p class="small spims-text-dim mb-0">
                {{ __('import.rolled_back_by', ['name' => $batch->rolledBackBy?->displayName() ?? '—', 'date' => $batch->rolled_back_at?->format('Y-m-d H:i')]) }}
            </p>
            @break
    @endswitch
</x-card>
@endsection
