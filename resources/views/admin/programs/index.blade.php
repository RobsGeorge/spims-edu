@extends('layouts.app')
@section('title', __('ui.nav_programs'))
@section('content')
<div class="admin-console animate-in">
    <x-page-header :title="__('ui.nav_programs')" :subtitle="__('hubs.programs_desc')">
        <x-slot:actions>
            <a href="{{ route('admin.programs.create') }}" class="btn btn-primary">{{ __('academics.create_program') }}</a>
        </x-slot:actions>
    </x-page-header>
    @if(session('status'))<div class="alert alert-success academic-alert">{{ session('status') }}</div>@endif
    <div class="spims-data-panel">
        <div class="spims-table-wrap spims-table-wrap--cards">
            <table class="table mb-0">
                <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('academics.name') }}</th><th>{{ __('academics.type') }}</th><th>{{ __('ui.status') }}</th></tr></thead>
                <tbody>
                @forelse($programs as $program)
                    <tr>
                        <td data-label="{{ __('academics.code') }}">
                            <a href="{{ route('admin.programs.show', $program) }}">{{ $program->code }}</a>
                            <a href="{{ route('admin.programs.edit', $program) }}" class="btn btn-sm btn-link">{{ __('ui.edit') }}</a>
                        </td>
                        <td data-label="{{ __('academics.name') }}">{{ $program->name }}</td>
                        <td data-label="{{ __('academics.type') }}"><x-badge :value="$program->type" /></td>
                        <td data-label="{{ __('ui.status') }}">{{ $program->active ? __('academics.active') : __('academics.inactive') }}</td>
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
    {{ $programs->links() }}
</div>
@endsection
