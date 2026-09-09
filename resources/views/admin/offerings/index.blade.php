@extends('layouts.app')
@section('title', __('ui.nav_offerings'))
@section('content')
<div class="admin-console animate-in">
    <x-page-header :title="__('ui.nav_offerings')" :subtitle="__('hubs.offerings_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.offerings.create') }}" class="btn btn-primary">{{ __('offerings.create_offering') }}</a>
        </x-slot:actions>
    </x-page-header>
    @if(session('status'))<div class="alert alert-success academic-alert">{{ session('status') }}</div>@endif
    <div class="spims-data-panel">
        <div class="spims-table-wrap spims-table-wrap--cards">
            <table class="table mb-0">
                <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('offerings.mode') }}</th><th>{{ __('offerings.semester') }}</th><th>{{ __('ui.status') }}</th></tr></thead>
                <tbody>
                @forelse($offerings as $offering)
                    <tr>
                        <td data-label="{{ __('academics.code') }}">
                            <a href="{{ route('admin.offerings.show', $offering) }}">{{ $offering->course->code }}</a>
                            <a href="{{ route('admin.offerings.edit', $offering) }}" class="btn btn-sm btn-link">{{ __('ui.edit') }}</a>
                        </td>
                        <td data-label="{{ __('offerings.mode') }}"><x-badge :value="$offering->mode" /></td>
                        <td data-label="{{ __('offerings.semester') }}">{{ $offering->semester?->name ?? '—' }}</td>
                        <td data-label="{{ __('ui.status') }}"><x-badge :value="$offering->status" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state :title="__('ui.no_results')" />
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $offerings->links() }}
</div>
@endsection
