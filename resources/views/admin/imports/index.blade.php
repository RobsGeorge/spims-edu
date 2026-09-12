@extends('layouts.app')

@section('title', __('import.title'))

@section('content')
<x-page-header :title="__('import.title')" :subtitle="__('import.subtitle')">
    <x-slot:actions>
        @if(app(\App\Support\AuthorizeService::class)->allows(auth()->user(), 'import.activate'))
            <a href="{{ route('admin.imports.activation') }}" class="btn btn-outline-primary">
                {{ __('import.activation_title') }}
            </a>
        @endif
        <a href="{{ route('admin.imports.sources.index') }}" class="btn btn-outline-primary">
            <x-icon name="settings" size="sm" /> {{ __('import.configure_sources') }}
        </a>
        <a href="{{ route('admin.imports.create') }}" class="btn btn-primary">
            <x-icon name="add" size="sm" /> {{ __('import.new_import') }}
        </a>
    </x-slot:actions>
</x-page-header>

@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if(session('warning'))<div class="alert alert-warning" role="status">{{ session('warning') }}</div>@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <x-stat :label="__('import.stat_students_imported')" :value="$stats['students_imported']" icon="student" />
    </div>
    <div class="col-6 col-lg-4">
        <x-stat :label="__('import.stat_pending_batches')" :value="$stats['pending_batches']" />
    </div>
    <div class="col-6 col-lg-4">
        <x-stat :label="__('import.stat_committed_batches')" :value="$stats['committed_batches']" icon="success" />
    </div>
</div>

<x-card variant="quiet" class="mb-4">
    <h2 class="h6 page-title mb-3">{{ __('import.sources_readiness') }}</h2>
    @forelse($sources as $source)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="spims-status-badge spims-status-{{ $source->active ? 'success' : 'neutral' }}">
                {{ $source->active ? __('import.source_active') : __('import.source_inactive') }}
            </span>
            <strong>{{ $source->name }}</strong>
            <span class="small spims-text-dim">{{ __('import.source_kind_'.$source->kind->value) }} &middot; {{ __('import.source_precedence') }} {{ $source->precedence }}</span>
            <span class="small spims-text-dim">&middot; {{ __('import.source_grade_mappings_count', ['count' => $source->gradeMappings()->count()]) }}</span>
        </div>
    @empty
        <p class="small spims-text-dim mb-0">{{ __('import.no_sources') }} <a href="{{ route('admin.imports.sources.index') }}">{{ __('import.add_source') }}</a></p>
    @endforelse
</x-card>

<x-card variant="panel">
    <h2 class="h6 page-title mb-3">{{ __('import.batches_title') }}</h2>

    @if($batches->isEmpty())
        <x-empty-state :title="__('import.empty_title')" :message="__('import.empty_message')" icon="bi-cloud-upload">
            <x-slot:actions>
                <a href="{{ route('admin.imports.create') }}" class="btn btn-primary btn-sm">{{ __('import.new_import') }}</a>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="table-responsive spims-table-wrap">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('import.col_source') }}</th>
                        <th>{{ __('import.col_entity') }}</th>
                        <th>{{ __('import.col_population') }}</th>
                        <th class="text-end">{{ __('import.col_rows') }}</th>
                        <th>{{ __('import.col_status') }}</th>
                        <th>{{ __('import.col_created') }}</th>
                        <th>{{ __('import.col_by') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($batches as $batch)
                    <tr>
                        <td>{{ $batch->source->name }}</td>
                        <td>{{ __('import.entity_'.$batch->entity_type->value) }}</td>
                        <td>{{ $batch->population ? __('import.population_'.$batch->population->value) : '—' }}</td>
                        <td class="text-end">{{ number_format($batch->row_count) }}</td>
                        <td><x-status-badge :status="strtolower($batch->status->value)" :label="__('import.status_'.$batch->status->value)" /></td>
                        <td class="small spims-text-dim">{{ $batch->created_at->format('Y-m-d') }}</td>
                        <td class="small spims-text-dim">{{ $batch->createdBy?->displayName() ?? '—' }}</td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.imports.show', $batch) }}">{{ __('import.open') }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $batches->links() }}</div>
    @endif
</x-card>
@endsection
