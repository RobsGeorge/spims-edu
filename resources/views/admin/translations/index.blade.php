@extends('layouts.app')

@section('title', __('hubs.translations'))

@section('content')
<x-page-header :title="__('hubs.translations')" :subtitle="__('hubs.translations_desc')" />

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if($pending->isEmpty())
    <x-empty-state :title="__('academics.translations_inbox_empty')" />
@else
    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('academics.entity') }}</th>
                        <th>{{ __('academics.field') }}</th>
                        <th>{{ __('academics.locale') }}</th>
                        <th>{{ __('academics.value') }}</th>
                        <th>{{ __('academics.source') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pending as $translation)
                    <tr>
                        <td>
                            <span class="small">{{ $translation->entity_type }}</span>
                            <div class="text-muted-theme small">{{ $translation->entity_id }}</div>
                        </td>
                        <td>{{ $translation->field }}</td>
                        <td>{{ strtoupper($translation->locale) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.translations.store') }}" class="d-flex flex-column gap-2">
                                @csrf
                                <input type="hidden" name="entity_type" value="{{ $translation->entity_type }}">
                                <input type="hidden" name="entity_id" value="{{ $translation->entity_id }}">
                                <input type="hidden" name="field" value="{{ $translation->field }}">
                                <input type="hidden" name="locale" value="{{ $translation->locale }}">
                                <textarea name="value" class="form-control form-control-sm" rows="2" required>{{ $translation->value }}</textarea>
                                <button class="btn btn-sm btn-outline-primary align-self-start">{{ __('ui.save') }}</button>
                            </form>
                        </td>
                        <td>
                            <x-status-badge
                                :status="$translation->source->value === 'AI' ? 'info' : 'neutral'"
                                :label="$translation->source->value"
                            />
                        </td>
                        <td class="text-nowrap">
                            <form method="POST" action="{{ route('admin.translations.verify', $translation) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success">{{ __('academics.verify_translation') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.translations.ai') }}" class="d-inline-flex flex-wrap gap-1 mt-1">
                                @csrf
                                <input type="hidden" name="entity_type" value="{{ $translation->entity_type }}">
                                <input type="hidden" name="entity_id" value="{{ $translation->entity_id }}">
                                <input type="hidden" name="field" value="{{ $translation->field }}">
                                <input type="hidden" name="source_locale" value="{{ $translation->locale }}">
                                <input type="hidden" name="source_text" value="{{ $translation->value }}">
                                <select name="target_locale" class="form-select form-select-sm" style="width:auto">
                                    @foreach(['ar','en','fr'] as $loc)
                                        @if($loc !== $translation->locale)
                                            <option value="{{ $loc }}">{{ strtoupper($loc) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-secondary">{{ __('academics.request_ai') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    {{ $pending->links() }}
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h6">{{ __('academics.add_translation') }}</h2>
        <form method="POST" action="{{ route('admin.translations.store') }}" class="row g-2">
            @csrf
            <div class="col-md-3"><input name="entity_type" class="form-control" placeholder="{{ __('academics.entity') }}" required></div>
            <div class="col-md-3"><input name="entity_id" class="form-control" placeholder="{{ __('academics.entity_id') }}" required></div>
            <div class="col-md-2"><input name="field" class="form-control" placeholder="{{ __('academics.field') }}" required></div>
            <div class="col-md-2">
                <select name="locale" class="form-select" required>
                    <option value="ar">AR</option>
                    <option value="en">EN</option>
                    <option value="fr">FR</option>
                </select>
            </div>
            <div class="col-12"><textarea name="value" class="form-control" rows="2" required></textarea></div>
            <div class="col-12"><button class="btn btn-primary btn-sm">{{ __('ui.save') }}</button></div>
        </form>
    </div>
</div>
@endsection
