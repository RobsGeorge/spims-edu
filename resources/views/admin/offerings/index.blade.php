@extends('layouts.app')
@section('title', __('ui.nav_offerings'))
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="spims-title mb-0">{{ __('ui.nav_offerings') }}</h1>
    <a href="{{ route('admin.offerings.create') }}" class="btn btn-primary">{{ __('offerings.create_offering') }}</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>{{ __('academics.code') }}</th><th>{{ __('offerings.mode') }}</th><th>{{ __('offerings.semester') }}</th><th>{{ __('ui.status') }}</th></tr></thead>
            <tbody>
            @foreach($offerings as $offering)
                <tr>
                    <td><a href="{{ route('admin.offerings.show', $offering) }}">{{ $offering->course->code }}</a></td>
                    <td>{{ $offering->mode->value }}</td>
                    <td>{{ $offering->semester?->name ?? '—' }}</td>
                    <td>{{ $offering->status->value }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
{{ $offerings->links() }}
@endsection
