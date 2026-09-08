@extends('layouts.app')
@section('title', __('ui.nav_programs'))
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="spims-title mb-0">{{ __('ui.nav_programs') }}</h1>
    <a href="{{ route('admin.programs.create') }}" class="btn btn-primary">{{ __('academics.create_program') }}</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<x-card variant="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('academics.name') }}</th><th>{{ __('academics.type') }}</th><th>{{ __('ui.status') }}</th></tr></thead>
            <tbody>
            @foreach($programs as $program)
                <tr>
                    <td>
                        <a href="{{ route('admin.programs.show', $program) }}">{{ $program->code }}</a>
                        <a href="{{ route('admin.programs.edit', $program) }}" class="btn btn-sm btn-link">{{ __('ui.edit') }}</a>
                    </td>
                    <td>{{ $program->name }}</td>
                    <td><x-badge :value="$program->type" /></td>
                    <td>{{ $program->active ? __('academics.active') : __('academics.inactive') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-card>
{{ $programs->links() }}
@endsection
